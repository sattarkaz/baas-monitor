<?php
require_once '_common.php';
session_destroy();
header('Location: index.php');
exit;
