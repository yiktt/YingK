<?php
/**
 * 关于
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
if ($this->hidden) {
    echo yingLockedPageHtml($this);
    $this->need('footer.php');
    return;
}
$this->need('about-head.php');
echo yingAboutMainHtml();
$this->need('footer.php');
