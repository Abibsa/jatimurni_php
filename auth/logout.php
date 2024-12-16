<?php
session_start();
session_unset();
session_destroy();

// Set flash message dalam cookie
setcookie('logout_message', 'Anda berhasil logout!', time() + 2, '/');
setcookie('logout_status', 'success', time() + 2, '/');

header("Location: ../login.php");
exit();
?> 