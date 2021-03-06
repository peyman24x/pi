<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 * @package         View
 */

namespace Pi\View\Helper;

use Pi;
use Pi\Application\Bootstrap\Resource\AdminMode;
use Zend\View\Helper\AbstractHelper;
use Module\System\Menu;

/**
 * Back-office navigation helper
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class AdminNav extends AbstractHelper
{
    /** @var string Module name */
    protected $module;

    /** @var string Leaf operation menu */
    protected $leafOperation = '';

    /** @var string Side menu content */
    //protected $side;

    /** @var string Top menu content */
    //protected $top;

    /**
     * Invoke for helper
     *
     * @param string $module
     * @return self
     */
    public function __invoke($module = '')
    {
        $this->module = $module ?: Pi::service('module')->current();

        return $this;
    }

    /**
     * Get back-office mode render
     *
     * @param string $class
     *
     * @return string
     */
    public function modes($class = '')
    {
        $mode = $_SESSION['PI_BACKOFFICE']['mode'];
        $modes = Menu::modes($mode);

        $pattern =<<<'EOT'
<li class="%s%s">
    <a href="%s">
        <i class="%s"></i>
        <span class="pi-mode-text">%s</span>
    </a>
</li>
EOT;
        $class = $class ?: 'nav';
        $content = sprintf('<ul class="%s">', $class);
        foreach ($modes as $mode) {
            $content .= sprintf(
                $pattern,
                $mode['active'] ? 'active' : '',
                $mode['link'] ? '' : 'disabled',
                $mode['link'] ? : 'javascript:void(0)',
                $mode['icon'],
                $mode['label']
            );
        }

        $content .= '</ul>';

        return $content;
    }

    /**
     * Get back-office side menu
     *
     * @param string $class
     *
     * @return string
     */
    public function main($class = '')
    {
        $module = $this->module ?: Pi::service('module')->currrent();
        $mode = $_SESSION['PI_BACKOFFICE']['mode'];

        $class = $class ?: 'nav';
        $content = sprintf('<ul class="%s">', $class);
        // Get manage mode navigation
        if (AdminMode::MODE_ADMIN == $mode && 'system' == $module) {
            $routeMatch = Pi::engine()->application()->getRouteMatch();
            $params = $routeMatch->getParams();
            if (empty($params['name'])) {
                $params['name'] = $_SESSION['PI_BACKOFFICE']['module'] ?: 'system';
            }
            $navigation = Menu::mainComponent(
                $params['name'],
                $params['controller']
            );

            $pattern =<<<'EOT'
<li class="%s">
    <a href="%s">
        <i class="%s"></i>
        <span class="pi-modules-nav-text">%s</span>
    </a>
</li>
EOT;
            foreach ($navigation as $item) {
                $content .= sprintf(
                    $pattern,
                    $item['active'] ? 'active' : '',
                    $item['href'],
                    $item['icon'] ? : 'fa fa-th',
                    $item['label']
                );
            }

        // Get operation mode navigation
        } elseif (AdminMode::MODE_ACCESS == $mode) {
            $navigation = Menu::mainOperation($module);

            $pattern =<<<'EOT'
<li class="%s">
    <a href="%s">
        <i class="%s"></i>
        <span class="pi-modules-nav-text">%s</span>
    </a>
</li>
EOT;

            foreach ($navigation as $item) {
                // Callback for hover action
                $callback = $item['callback'];

                $content .= sprintf(
                    $pattern,
                    $item['active'] ? 'active' : '',
                    $item['href'],
                    $item['icon'] ? : 'fa fa-th',
                    $item['label']
                );
            }

        }

        $content .= '</ul>';

        return $content;
    }

    /**
     * Get back-office sub menu
     *
     * @param array|string $options
     *
     * @return string
     */
    public function ____sub($options = array())
    {
        $module = $this->module ?: Pi::service('module')->currrent();
        $mode   = $_SESSION['PI_BACKOFFICE']['mode'];

        if (is_string($options)) {
            $options = array('ulClass' => $options);
        }
        if (!isset($options['ulClass'])) {
            $options['ulClass'] = 'nav pi-modules-nav-sub';
        }
        $navigation = '';
        // Managed components
        if (AdminMode::MODE_ADMIN == $mode && 'system' == $module) {

        // Module operations
        } elseif (AdminMode::MODE_ACCESS == $mode) {
            if (!isset($options['sub'])) {
                $options['sub'] = array(
                    'ulClass'   => 'nav nav-tabs',
                    'maxDepth'  => 0,
                );
            }
            list($navigation, $leaf) = Menu::subOperation($module, $options);
            $this->leafOperation = $leaf;
        }

        return $navigation;
    }

    /**
     * Get back-office top menu
     *
     * @param array|string $options
     *
     * @return string
     */
    public function top($options = array())
    {
        $module = $this->module ?: Pi::service('module')->currrent();
        $mode   = $_SESSION['PI_BACKOFFICE']['mode'];

        if (is_string($options)) {
            $options = array('ulClass' => $options);
        }
        if (!isset($options['ulClass'])) {
            $options['ulClass'] = 'nav nav-tabs';
        }

        $navigation = '';
        // Managed components
        if (AdminMode::MODE_ADMIN == $mode && 'system' == $module) {
            $currentModule = $_SESSION['PI_BACKOFFICE']['module'];
            $navigation = Menu::subComponent($currentModule, $options);
        // Module operations
        } elseif (AdminMode::MODE_ACCESS == $mode) {
            if (!isset($options['sub'])) {
                $options['sub'] = array(
                    'ulClass'   => 'nav nav-pills',
                    'maxDepth'  => 0,
                );
            }
            list($parent, $leaf) = Menu::subOperation($module, $options);
            $navigation = $parent . $leaf;
        }

        return $navigation;
    }
}
