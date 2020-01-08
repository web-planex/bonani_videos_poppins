<?php

if (IS_LOGGED == false) {
    $data = array(
        'status' => 400,
        'error' => 'Not logged in'
    );
    echo json_encode($data);
    exit();
}
if (PT_IsAdmin() == false) {
    $data = array(
        'status' => 400,
        'error' => 'Not admin'
    );
    echo json_encode($data);
    exit();
}
define('FCPATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
$f = '';
if (isset($_GET['f'])) {
    $f = PT_Secure($_GET['f'], 0);
}

$version = '';
if ($f == 'run_updater') {

    $version = $pt->config->version;
    $arrContextOptions = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false
        )
    );

    $tmp_dir = $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();

    if (!$tmp_dir) {
        $tmp_dir = @sys_get_temp_dir();
        if (!$tmp_dir) {
            $tmp_dir = 'temp';
        }
    }

    $tmp_dir = rtrim($tmp_dir, '/') . '/';

    if (!is_writable($tmp_dir)) {
        header('HTTP/1.0 400');
        echo json_encode(array(
            "Temporary directory not writable - <b>$tmp_dir</b><br />Please contact your hosting provider make this directory writable. The directory needs to be writable for the upgrade files.",
        ));
        die;
    }
    $version_info = PT_GetVersionInfo();
    $latest_version = $version_info['latest_version'];
    $tmp_dir = $tmp_dir . 'v' . $latest_version . '/';
    $tmp_upgrade_dir = $tmp_dir;
    if (!is_dir($tmp_dir)) {
        mkdir($tmp_dir);
        fopen($tmp_dir . 'index.html', 'w');
    }
    $zipFile = $tmp_dir . $latest_version . '.zip'; // Local Zip File Path
    $zipResource = fopen($zipFile, "w+");
    // Get The Zip File From Server
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pt->version_download_url);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FILE, $zipResource);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'current_version' => $version,
    ));
    $success = curl_exec($ch);

    if (!$success) {
        clean_tmp_files($tmp_upgrade_dir, $tmp_dir);
        header('HTTP/1.0 400 Bad error');
        echo json_encode(array(
            curl_error($ch),
        ));
        die;
    }
    curl_close($ch);
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === true) {
        if (!$zip->extractTo(FCPATH)) {
            header('HTTP/1.0 400 Bad error');
            echo json_encode(array(
                'Failed to extract downloaded zip file',
            ));
        }
        $zip->close();
    } else {
        header('HTTP/1.0 400 Bad error');
        echo json_encode(array(
            'Failed to open downloaded zip file',
        ));
    }
    if (file_exists('run_update.php')) {

        $file = file_get_contents($site_url . "/run_update.php?updated", false, stream_context_create($arrContextOptions));
        $data['status'] = 200;
    }
    header("Content-type: application/json");
    echo json_encode($data);
    exit();
}

function clean_tmp_files($tmp_upgrade_dir, $tmp_dir) {
    if (is_dir($tmp_upgrade_dir)) {
        if (@!delete_dir($tmp_upgrade_dir)) {
            @rename($tmp_upgrade_dir, $tmp_dir . 'delete_this_' . uniqid());
        }
    }
}
