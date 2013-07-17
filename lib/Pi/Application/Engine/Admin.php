<?php
/**
 * Admin application engine class
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       Copyright (c) Pi Engine http://www.xoopsengine.org
 * @license         http://www.xoopsengine.org/license New BSD License
 * @author          Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 * @package         Pi\Application
 */

namespace Pi\Application\Engine;

/**
 * Pi admin application engine
 *
 * Tasks: load configs, default listeners, module manager, bootstrap, application; boot application; run application
 *
 * @author      Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Admin extends Standard
{
    const SECTION = 'admin';

    /**
     * Identifier for file name of option data
     * @var string
     */
    protected $fileIdentifier = 'admin';
}
