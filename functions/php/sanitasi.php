<?php
function sani($data)
{
    if (is_array($data)) {
        return array_map('sani', $data);
    }
    if (is_string($data)) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8', false);
        return $data;
    }
    return $data;
}
