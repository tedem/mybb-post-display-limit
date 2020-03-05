<?php

/**
 * POST DISPLAY LIMIT
 *
 * @author      tedem <tedemdev@gmail.com>
 * @copyright   2020 [@author]
 */

if (! defined('IN_MYBB')) {
    die('(-_*) This file cannot be accessed directly.');
}

// constants
define('POST_DISPLAY_LIMIT_ID', 'post_display_limit');
define('POST_DISPLAY_LIMIT_NAME', 'İleti Görüntüleme Limiti');
define('POST_DISPLAY_LIMIT_VERSION', '1.0.0');

// hooks
if (! defined('IN_ADMINCP')) {
    $plugins->add_hook("showthread_start", "post_display_limit_run");
}

function post_display_limit_info()
{
    return [
        'name'          => POST_DISPLAY_LIMIT_NAME,
        'description'   => 'Ziyaretçiler için ileti görüntüleme limiti belirlemenizi sağlar. (Limit aşıldığında giriş ya da kayıt olmalarını gerektirir.)',
        'website'       => 'https://mybbcode.com/',
        'author'        => 'tedem',
        'authorsite'    => 'https://wa.me/905300641197',
        'version'       => POST_DISPLAY_LIMIT_VERSION,
        'codename'      => 'md_' . POST_DISPLAY_LIMIT_ID,
        'compatibility' => '18*'
    ];
}

function post_display_limit_install()
{
    global $db, $cache;

    $info = post_display_limit_info();

    // add cache
    $md = $cache->read('md');

    $md[$info['codename']] = [
        'name'      => $info['name'],
        'author'    => $info['author'],
        'version'   => $info['version'],
    ];

    $cache->update('md', $md);

    // add settings
    $group = [
        'name'          => $db->escape_string($info['codename']),
        'title'         => $db->escape_string(POST_DISPLAY_LIMIT_NAME),
        'description'   => $db->escape_string('Ayarlar: ' . POST_DISPLAY_LIMIT_NAME),
        'isdefault'     => 0,
    ];

    $query = $db->simple_select('settinggroups', 'gid', "name = '{$info['codename']}'");

    if ($gid = (int) $db->fetch_field($query, 'gid')) {
        $db->update_query('settinggroups', $group, "gid = '{$gid}'");
    } else {
        $query = $db->simple_select('settinggroups', 'MAX(disporder) AS disporder');

        $disporder  = (int) $db->fetch_field($query, 'disporder');

        $group['disporder'] = ++$disporder;

        $gid = (int) $db->insert_query('settinggroups', $group);
    }

    // _active
    $setting = [
        "name"          => $db->escape_string("{$info['codename']}_active"),
        "title"         => $db->escape_string('Eklenti Durumu'),
        "description"   => $db->escape_string('Eklentiyi aktif etmek için <b>EVET</b>, pasif etmek için <b>HAYIR</b> olarak belirtin.'),
        "optionscode"   => "yesno",
        "value"         => 1,
        "disporder"     => 1,
        "gid"           => $gid,
    ];

    $db->insert_query('settings', $setting);

    // _limit
    $setting = [
        "name"          => $db->escape_string("{$info['codename']}_limit"),
        "title"         => $db->escape_string('İleti Görüntüleme Limiti'),
        "description"   => $db->escape_string('Ziyaretçinin görüntüleyebileceği en fazla ileti sayısını belirtin. (Varsayılan: 5)'),
        "optionscode"   => "text",
        "value"         => 5,
        "disporder"     => 2,
        "gid"           => $gid,
    ];

    $db->insert_query('settings', $setting);

    // _text
    $setting = [
        "name"          => $db->escape_string("{$info['codename']}_text"),
        "title"         => $db->escape_string('Limit Metni'),
        "description"   => $db->escape_string('
<div>Görüntülenebilecek en fazla ileti sayısı aşıldıktan sonra ziyaretçiye gösterilecek metni belirleyin.</div>
<div>Aşağıdaki belirlenmiş parametleri metin içerisinde kullanabilirsiniz;</div>
<div><b>[LIMIT]:</b> Belirlediğiniz görüntüleme limitidir.</div>
<div><b>[LOGIN]:</b> Giriş bağlantısı oluşturur.</div>
<div><b>[REGISTER]:</b> Kayıt bağlantısı oluşturur.</div>'),
        "optionscode"   => "textarea",
        "value"         => $db->escape_string('
<div>Merhaba Ziyaretçi! Sizin için belirlediğimiz en fazla ([LIMIT]) ileti (konu / yorum) görüntüleme sayısına ulaştığınızı üzülerek belirtmek isteriz.</div>
<div>Üzülmeyin; topluluğumuza <b>[LOGIN]</b> yaparak ya da <b>[REGISTER]</b> olarak tüm içeriklerimizden hiç bir kısıtlama olmadan yararlanabilirsiniz.</div>'),
        "disporder"     => 3,
        "gid"           => $gid,
    ];

    $db->insert_query('settings', $setting);

    rebuild_settings();
}

function post_display_limit_is_installed()
{
    global $cache;

    $info = post_display_limit_info();

    $md = $cache->read('md');

    if ($md[$info['codename']]) {
        return true;
    }
}

function post_display_limit_uninstall()
{
    global $db, $cache;

    $info = post_display_limit_info();

    // remove cache
    $md = $cache->read('md');

    unset($md[$info['codename']]);

    $cache->update('md', $md);

    if (count($md) == 0) {
        $db->delete_query('datacache', "title='md'");
    }

    // remove settings
    $db->delete_query('settinggroups', "name = '{$info['codename']}'");

    $db->delete_query('settings', "name = '{$info['codename']}_active'");
    $db->delete_query('settings', "name = '{$info['codename']}_limit'");
    $db->delete_query('settings', "name = '{$info['codename']}_text'");

    rebuild_settings();
}

function post_display_limit_activate()
{
    // ...
}

function post_display_limit_deactivate()
{
    // ...
}

function post_display_limit_run()
{
    global $mybb, $lang;

    if ($mybb->user['usergroup'] != "1" || $mybb->settings['md_post_display_limit_active'] != 1) {
        return;
    }

    if (! isset($mybb->cookies['md_post_display_limit'])) {
        my_setcookie('md_post_display_limit', 1);
    } else {
        $postDisplayLimit = $mybb->cookies['md_post_display_limit'] + 1;

        my_setcookie('md_post_display_limit', $postDisplayLimit);
    }

    if ($mybb->cookies['md_post_display_limit'] > $mybb->settings['md_post_display_limit_limit']) {
        $lang->load('global');

        $errorMessage = <<<MESSAGE
{$mybb->settings['md_post_display_limit_text']}
MESSAGE;

        $errorMessage = preg_replace(
            "!\[LIMIT\]!Us",
            $mybb->settings['md_post_display_limit_limit'],
            $errorMessage);

        $errorMessage = preg_replace(
            "!\[REGISTER\]!Us",
            '<a href="member.php?action=register" rel="nofollow noopener">' . $lang->welcome_register . '</a>',
            $errorMessage);

        $errorMessage = preg_replace(
            "!\[LOGIN\]!Us",
            '<a href="member.php?action=login" rel="nofollow noopener">' . $lang->welcome_login . '</a>',
            $errorMessage);

        error($errorMessage);
    }
}
