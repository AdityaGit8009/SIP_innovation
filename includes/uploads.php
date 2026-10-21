<?php
function sip_valid_image_upload(string $tmp, string $extension): bool
{
    $allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
    if (!isset($allowed[$extension]) || !is_uploaded_file($tmp) || filesize($tmp) > 5*1024*1024) return false;
    $info=@getimagesize($tmp);
    return is_array($info) && ($info['mime'] ?? '') === $allowed[$extension]
        && (new finfo(FILEINFO_MIME_TYPE))->file($tmp) === $allowed[$extension];
}
