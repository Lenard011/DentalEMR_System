<?php
session_start();
session_unset();
session_destroy();
header("Location: /dentalemr_system/html/login/login.html");
exit;
