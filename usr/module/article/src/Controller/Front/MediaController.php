<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link         http://code.pialog.org for the Pi Engine source repository
 * @copyright    Copyright (c) Pi Engine http://pialog.org
 * @license      http://pialog.org/license.txt New BSD License
 */

namespace Module\Article\Controller\Front;

use Pi\Mvc\Controller\ActionController;
use Pi;
use Pi\Paginator\Paginator;
use Module\Article\Form\MediaEditForm;
use Module\Article\Form\MediaEditFilter;
use Module\Article\Form\SimpleSearchForm;
use Module\Article\Service;
use Zend\Db\Sql\Expression;
use Module\Article\Media;
use Module\Article\File;
use Pi\File\Transfer\Upload as UploadHandler;
use ZipArchive;

/**
 * Media controller
 * 
 * Feature list
 * 
 * 1. AJAX action for checking upload file
 * 2. AJAX action for saving media
 * 3. AJAX action for removing media
 * 4. AJAX action for search media
 * 5. Download media
 * 
 * @author Zongshu Lin <lin40553024@163.com>
 */
class MediaController extends ActionController
{
    const AJAX_RESULT_TRUE  = true;
    const AJAX_RESULT_FALSE = false;

    /**
     * Saving media information
     * 
     * @param  array    $data  Media information
     * @return boolean
     * @throws \Exception 
     */
    protected function saveMedia($data)
    {
        $module        = $this->getModule();
        $modelMedia    = $this->getModel('media');
        $fakeId        = $image = null;

        if (isset($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
        }

        $fakeId = Service::getParam($this, 'fake_id', 0);

        unset($data['media']);
        
        // Getting media info
        $session    = Service::getUploadSession($module, 'media');
        if (isset($session->$id)
            || ($fakeId && isset($session->$fakeId))) {
            $uploadInfo = isset($session->$id)
                ? $session->$id : $session->$fakeId;

            if ($uploadInfo) {
                $pathInfo = pathinfo($uploadInfo['tmp_name']);
                if ($pathInfo['extension']) {
                    $data['type'] = strtolower($pathInfo['extension']);
                }
                $data['size'] = filesize($uploadInfo['tmp_name']);
                
                // Meta
                $metaColumns = array('w', 'h');
                $meta        = array();
                foreach ($uploadInfo as $key => $info) {
                    if (in_array($key, $metaColumns)) {
                        $meta[$key] = $info;
                    }
                }
                $data['meta'] = json_encode($meta);
            }

            unset($session->$id);
            unset($session->$fakeId);
        }
        
        // Getting user ID
        $user   = Pi::service('user')->getUser();
        $uid    = Pi::user()->getId() ?: 0;
        $data['uid'] = $uid;
        
        if (empty($id)) {
            $data['time_upload'] = time();
            $row = $modelMedia->createRow($data);
            $row->save();
            $id = $row->id;
            $rowMedia = $modelMedia->find($id);
        } else {
            $data['time_update'] = time();
            $rowMedia = $modelMedia->find($id);

            if (empty($rowMedia)) {
                return false;
            }

            $rowMedia->assign($data);
            $rowMedia->save();
        }

        // Save image
        if (!empty($uploadInfo)) {
            $fileName = $rowMedia->id;

            if ($pathInfo['extension']) {
                $fileName .= '.' . $pathInfo['extension'];
            }
            $fileName = $pathInfo['dirname'] . '/' . $fileName;

            $rowMedia->url = rename(
                Pi::path($uploadInfo['tmp_name']),
                Pi::path($fileName)
            ) ? $fileName : $uploadInfo['tmp_name'];
            $rowMedia->save();
        }

        return $id;
    }
    
    /**
     * Output file
     * 
     * @param array $options 
     */
    protected function httpOutputFile(array $options)
    {
        if ((!isset($options['file']) && !isset($options['raw']))) {
            if (!$options['silent']) {
                header('HTTP/1.0 404 Not Found');
            }
            exit();
        }
        if (isset($options['file']) && !is_file($options['file'])) {
            if (!$options['silent']) {
                header('HTTP/1.0 403 Forbidden');
            }
            exit();
        }
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
            $options['fileName'] = urlencode($options['fileName']);
        }
        $options['fileSize'] = isset($options['file']) 
            ? filesize($options['file']) : strlen($options['raw']);

        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header("Pragma: public");
        header('Content-Description: File Transfer');
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') === false) {
            header('Content-Type: application/force-download; charset=UTF-8');
        } else {
            header('Content-Type: application/octet-stream; charset=UTF-8');
        }
        header('Content-Disposition: attachment; filename="' . $options['fileName'] . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Pragma: public');
        header('Content-Length: ' . $options['fileSize']);
        ob_clean();
        flush();

        if (!empty($options['file'])) {
            readfile($options['file']);
            if (!empty($options['deleteFile'])) {
                @unlink($options['file']);
            }
        } else {
            echo $options['raw'];
            ob_flush();
            flush();
        }
        if (empty($options['notExit'])) {
            exit();
        }
    }
    
    /**
     * Calculate the image size by allowed image size.
     * 
     * @param string|array  $image
     * @param array         $allowSize
     * @return array
     * @throws \Exception 
     */
    public static function scaleImageSize($image, $allowSize)
    {
        if (is_string($image)) {
            $imageSizeRaw = getimagesize($image);
            $imageSize['w'] = $imageSizeRaw[0];
            $imageSize['h'] = $imageSizeRaw[1];
        } else {
            $imageSize = $image;
        }
        
        if (!isset($imageSize['w']) or !isset($imageSize['h'])) {
            throw \Exception(_a('Raw image width and height data is needed!'));
        }
        
        if (!isset($allowSize['image_width'])
            or !isset($allowSize['image_height'])
        ) {
            throw \Exception(_a('The limitation data is needed!'));
        }
        
        $scaleImage = $imageSize;
        if ($imageSize['w'] >= $imageSize['h']) {
            if ($imageSize['w'] > $allowSize['image_width']) {
                $scaleImage['w'] = (int) $allowSize['image_width'];
                $scaleImage['h'] = (int) (($allowSize['image_width']
                                 * $imageSize['h'])
                                 / $imageSize['w']);
            }
        } else {
            if ($imageSize['h'] > $allowSize['image_height']) {
                $scaleImage['h'] = (int) $allowSize['image_height'];
                $scaleImage['w'] = (int) (($allowSize['image_height'] 
                                 * $imageSize['w'])
                                 / $imageSize['h']);
            }
        }
        
        return $scaleImage;
    }
    
    /**
     * Processing media uploaded. 
     */
    public function uploadAction()
    {
        Pi::service('log')->active(false);
        
        $module   = $this->getModule();
        $config   = Pi::service('module')->config('', $module);

        $return   = array('status' => false);
        $id       = Service::getParam($this, 'id', 0);
        if (empty($id)) {
            $id = Service::getParam($this, 'fake_id', 0) ?: uniqid();
        }
        $type     = Service::getParam($this, 'type', 'attachment');
        $formName = Service::getParam($this, 'form_name', 'upload');

        // Checking whether ID is empty
        if (empty($id)) {
            $return['message'] = _a('Invalid ID!');
            return json_encode($return);
            exit ;
        }
        
        $width   = Service::getParam($this, 'width', 0);
        $height  = Service::getParam($this, 'height', 0);
        
        $rawInfo     = $this->request->getFiles($formName);
        $ext         = strtolower(
            pathinfo($rawInfo['name'], PATHINFO_EXTENSION)
        );
        $rename  = $id . '.' . $ext;

        $allowedExtension = ($type == 'image')
            ? $config['image_extension'] : $config['media_extension'];
        $mediaSize        = ($type == 'image')
            ? $config['max_image_size'] : $config['max_media_size'];
        $destination = Service::getTargetDir('media', $module, true, true);
        $upload      = new UploadHandler;
        $upload->setDestination(Pi::path($destination))
                ->setRename($rename)
                ->setExtension($allowedExtension)
                ->setSize($mediaSize);
        
        // Get raw file name
        if (empty($rawInfo)) {
            $content = $this->request->getContent();
            preg_match('/filename="(.+)"/', $content, $matches);
            $rawName = $matches[1];
        } else {
            $rawName = null;
        }
        
        // Checking whether uploaded file is valid
        if (!$upload->isValid($rawName)) {
            $return['message'] = implode(', ', $upload->getMessages());
            echo json_encode($return);
            exit ;
        }

        $upload->receive();
        $fileName = $destination . '/' . $rename;
        
        // Resolve allowed image extension
        $imageExt = explode(',', $config['image_extension']);
        foreach ($imageExt as &$value) {
            $value = strtolower(trim($value));
        }
        
        // Scale image if file is image file
        $uploadInfo['tmp_name'] = $fileName;
        $imageSize              = array();
        if (in_array($ext, $imageExt)) {
            $scaleImageSize = $this->scaleImageSize(
                Pi::path($fileName),
                $config
            );
            $uploadInfo['w'] = $width ?: $scaleImageSize['w'];
            $uploadInfo['h'] = $height ?: $scaleImageSize['h'];
            Service::saveImage($uploadInfo);
            
            $imageSizeRaw = getimagesize(Pi::path($fileName));
            $imageSize['w'] = $imageSizeRaw[0];
            $imageSize['h'] = $imageSizeRaw[1];
        }

        // Save uploaded media
        $rowMedia = $this->getModel('media')->find($id);
        if ($rowMedia) {
            if ($rowMedia->url && $rowMedia->url != $fileName) {
                unlink(Pi::path($rowMedia->url));
            }
            
            $rowMedia->url  = $fileName;
            $rowMedia->type = $ext;
            $rowMedia->size = filesize(Pi::path($fileName));
            $rowMedia->meta = json_encode($imageSize);
            $rowMedia->save();
        } else {
            // Or save info to session
            $session = Service::getUploadSession($module, 'media');
            $session->$id = $uploadInfo;
        }
        
        // Prepare return data
        $return['data'] = array_merge(
            array(
                'originalName' => $rawInfo['name'],
                'size'         => $rawInfo['size'],
                'preview_url'  => Pi::url($fileName),
                'basename'     => basename($fileName),
                'type'         => $ext,
                'id'           => $id,
                'filename'     => $fileName,
            ),
            $imageSize
        );
        $return['status'] = true;
        echo json_encode($return);
        exit;
    }
    
    /**
     * Remove uploaded but not saved media.
     * 
     * @return ViewModel 
     */
    public function removeAction()
    {
        Pi::service('log')->active(false);
        $id           = Service::getParam($this, 'id', 0);
        $fakeId       = Service::getParam($this, 'fake_id', 0);
        $affectedRows = 0;
        $module       = $this->getModule();

        if ($id) {
            $row = $this->getModel('media')->find($id);

            if ($row && $row->url) {
                // Delete media
                unlink(Pi::path($row->url));

                // Update db
                $row->url  = '';
                $row->type = '';
                $row->size = 0;
                $row->meta = '';
                $affectedRows = $row->save();
            }
        } else if ($fakeId) {
            $session = Service::getUploadSession($module, 'media');

            if (isset($session->$fakeId)) {
                $uploadInfo = isset($session->$id) ? $session->$id : $session->$fakeId;

                unlink(Pi::path($uploadInfo['tmp_name']));

                unset($session->$id);
                unset($session->$fakeId);
            }
            $affectedRows = 1;
        }

        echo json_encode(array(
            'status'    => $affectedRows ? self::AJAX_RESULT_TRUE : self::AJAX_RESULT_FALSE,
            'message'   => 'ok',
        ));
        exit;
    }
    
    /**
     * Downloading a media.
     * 
     * @return ViewModel
     * @throws \Exception 
     */
    public function downloadAction()
    {
        $id     = $this->params('id', 0);
        $ids    = array_filter(explode(',', $id));

        if (empty($ids)) {
            throw new \Exception(_a('Invalid media ID!'));
        }
        
        // Export files
        $rowset     = $this->getModel('media')->select(array('id' => $ids));
        $affectRows = array();
        $files      = array();
        foreach ($rowset as $row) {
            if (!empty($row->url) and file_exists(Pi::path($row->url))) {
                $files[]      = Pi::path($row->url);
                $affectRows[] = $row->id;
            }
        }
        
        if (empty($affectRows)) {
            return $this->jumpTo404(_a('Can not find file!'));
        }
        
        // Statistics
        $model  = $this->getModel('media_statistics');
        $rowset = $model->select(array('media' => $affectRows));
        $exists = array();
        foreach ($rowset as $row) {
            $exists[] = $row->media;
        }
        
        if (!empty($exists)) {
            foreach ($exists as $item) {
                $row = $model->find($item, 'media');
                $row->download = $row->download + 1;
                $row->save();
            }
        }
        
        $newRows = array_diff($affectRows, $exists);
        foreach ($newRows as $item) {
            $data = array(
                'media'    => $item,
                'download' => 1,
            );
            $row = $model->createRow($data);
            $row->save();
        }
        
        $filePath = 'upload/temp';
        File::mkdir($filePath);
        $filename = sprintf('%s/media-%s.zip', $filePath, time());
        $filename = Pi::path($filename);
        $zip      = new ZipArchive();
        if ($zip->open($filename, ZIPARCHIVE::CREATE)!== TRUE) {
            exit ;
        }
        $compress = count($files) > 1 ? true : false;
        if ($compress) {
            foreach( $files as $file) {
                if (file_exists($file)) {  
                    $zip->addFile( $file , basename($file));
                }
            }  
            $zip->close();
        } else {
            $filename = Pi::path(array_shift($files));
        }
        
        $options = array(
            'file'       => $filename,
            'fileName'   => basename($filename),
        );
        if ($compress) {
            $options['deleteFile'] = true;
        }
        $this->httpOutputFile($options, $this);
    }
    
    /**
     * Searching image details by AJAX according to posted parameters. 
     */
    public function searchAction()
    {
        Pi::service('log')->active(false);
        
        $type = Service::getParam($this, 'type', '');
        
        $where = array();
        // Resolving type
        if ($type == 'image') {
            $extensionDesc = $this->config('image_extension');
        } else {
            $extensionDesc = $type;
        }
        $extensions = explode(',', $extensionDesc);
        foreach ($extensions as &$ext) {
            $ext = trim($ext);
        }
        $types = array_filter($extensions);
        if (!empty($types)) {
            $where['type'] = $types;
        }
        
        // Resolving ID
        $id  = Service::getParam($this, 'id', 0);
        $ids = array_filter(explode(',', $id));
        if (!empty($ids)) {
            $where['id'] = $ids;
        }
        
        // Getting title condition
        $title = Service::getParam($this, 'title', '');
        if (!empty($title)) {
            $where['title like ?'] = '%' . $title . '%';
        }
        
        $page   = (int) Service::getParam($this, 'page', 1);
        $limit  = (int) Service::getParam($this, 'limit', 5);
        
        $rowset = Media::getList($where, $page, $limit);
        
        // Get count
        $model  = $this->getModel('media');
        $select = $model->select()
            ->where($where)
            ->columns(array('count' => new Expression('count(*)')));
        $count  = $model->selectWith($select)->current()->count;
        
        // Get previous and next button URL
        $prevUrl = '';
        $nextUrl = '';
        $params  = array(
            'action'    => 'search',
            'type'      => $type ?: 0,
            'id'        => $id ?: 0,
            'title'     => $title ?: 0,
            'limit'     => $limit ?: 0,
        );
        $params = array_filter($params);
        if ($count > $page * $limit) {
            $params['page'] = $page + 1;
            $nextUrl = $this->url('', $params);
        }
        if ($page > 1) {
            $params['page'] = $page - 1;
            $prevUrl = $this->url('', $params);
        }
        
        echo json_encode(array(
            'data'      => $rowset,
            'prev_url'  => $prevUrl,
            'next_url'  => $nextUrl,
        ));
        exit();
    }
    
    /**
     * Saving image into media table by AJAX. 
     */
    public function saveAction()
    {
        Pi::service('log')->active(false);
        
        $id     = $this->params('id', 0);
        $fakeId = $this->params('fake_id', 0);
        $source = $this->params('source', 'outside');
        $result = array();
        if (empty($fakeId) and empty($id)) {
            $result = array(
                'status'     => self::AJAX_RESULT_FALSE,
                'data'       => array(
                    'message'   => _a('Invalid ID!'),
                ),
            );
        } else {
            $data = array();
            if ($id) {
                $data['id'] = $id;
            } else {
                $data = array(
                    'id'    => 0,
                    'name'  => $fakeId,
                    'title' => 'File ' . $fakeId . ' from ' . $source,
                );
            }
            $mediaId = $this->saveMedia($data);
            if (empty($mediaId)) {
                $result = array(
                    'status'     => self::AJAX_RESULT_FALSE,
                    'data'       => array(
                        'message'   => _a('Can not save data!'),
                    ),
                );
            } else {
                $result = array(
                    'status'     => self::AJAX_RESULT_TRUE,
                    'data'       => array(
                        'id'        => $mediaId,
                        'newid'     => uniqid(),
                        'message'   => _a('Media data saved successful!'),
                    ),
                );
            }
        }
        
        echo json_encode($result);
        exit;
    }
}
