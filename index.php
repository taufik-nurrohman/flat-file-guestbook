<?php session_start(); ?>
<!DOCTYPE html>
<html dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Flat-File Guestbook</title>
    <link href="style.css" rel="stylesheet">
  </head>
  <body>

    <div class="guestbook" id="guestbook">
      <div class="guest-messages" id="guest-messages">
<?php

// ===================================================================
// PHP FLAT-FILE GUESTBOOK by Taufik Nurrohman <http://gplus.to/tovic>
// ===================================================================

// START CONFIGURATION
$database = 'database-001'; // Your TXT file name as database.
$per_page = 5; // The number of items you want to display per page.
$time_zone = 'Asia/Jakarta'; // Look at `date_default_timezone_set()`
$max_length_name = 60; // Maximum character length for guest name
$max_length_url = 120; // Maximum character length for guest URL
$max_length_message = 1000; // Maximum character length for guest message
$messages = array(
    'database_missing' => 'Database not found. Created one. Please reload the page.',
    'name_missing' => 'Please enter your name.',
    'url_invalid' => 'Invalid URL.',
    'message_missing' => 'Please enter your message.',
    'math_invalid' => 'Wrong math answer.',
    'max_length_name' => 'Maximum character length for guest name is ' . $max_length_name,
    'max_length_url' => 'Maximum character length for guest URL is ' . $max_length_url,
    'max_length_message' => 'Maximum character length for guest message is ' . $max_length_message,
    'no_content' => 'No content.'
);
// END CONFIGURATION

// Set default timezone to adjust the timestamp.
// => http://www.php.net/manual/en/function.date-default-timezone-set.php
date_default_timezone_set($time_zone);

// Functions to create and/or update the content of the TXT file (our database)
function create_or_update_file($file_path, $data) {
    $handle = fopen($file_path, 'w') or die('Cannot open file: ' . $file_path);
    fwrite($handle, $data);
}

// Filter HTML outputs. 
// The rest will appear as plain HTML entities to prevent XSS. 
// => http://en.wikipedia.org/wiki/Cross-site_scripting
function filter_html($data) {
    return preg_replace(
        array(
            '/&lt;(\/?)(b|blockquote|br|em|i|ins|mark|q|strong|u)&gt;/i', // Allowed HTML tags
            '/&lt;center&gt;/', // Deprecated <center> tag
            '/&lt;\/center&gt;/', // Deprecated </center> tag
            '/&amp;([a-zA-Z]+|\#[0-9]+);/' // Symbols
        ),
        array(
            '<$1$2>',
            '<div style="text-align:center;">',
            '</div>',
            '&$1;'
        ),
    $data);
}

// Redefine database name via URL to load
// Load database-002.txt => http://localhost/guestbook/index.php&data=database-002
if(isset($_GET['data'])) {
    $database = $_GET['data'];
}

// Check whether the "database" is not available. If not, create one!
if( ! file_exists($database . '.txt')) {
    // Prevent guest to create new database via `data=database-XXX` in URL
    // Only administrator can do this by editing the `$database` value
    if( ! isset($_GET['data'])) {
        create_or_update_file($database . '.txt', "");
        echo "<p class=\"message-warning\">" . $messages['database_missing'] . "</p>";
    }
    return false;
} else {
    $old_data = file_get_contents($database . '.txt');
}


/**
 * Post a message
 */
$error = ""; // error messages
if($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = "";
    $url = "";
    $message = "";
    $timestamp = date('U');

    // Make sure the guest name is not empty.
    if(isset($_POST['name']) && ! empty($_POST['name'])) {
        $name = strip_tags($_POST['name']);
    } else {
        $error .= "<p class=\"message-error\">" . $messages['name_missing'] . "</p>";
    }

    // Make sure the URL format is valid. Set its value as `-` if empty. 
    if(isset($_POST['url']) && ! empty($_POST['url'])) {
        if(filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
            $url = strip_tags($_POST['url']);
        } else {
            $error .= "<p class=\"message-error\">" . $messages['url_invalid'] . "</p>";
        }
    } else {
        $url = "-";
    }

    // Make sure the guest message is not empty.
    if(isset($_POST['message']) && ! empty($_POST['message'])) {
        $message = preg_replace(
            array(
                '/[\n\r]{4,}/', // [1]
                '/\n/',
                '/[\r\t]/',
                '/ {2}/', // Multiple space characters
                '/ &nbsp;|&nbsp; /',
                '/<a (.*?)?href=(\'|\")(.*?)(\'|\")(.*?)?>(.*?)<\/a>/i' // Match links
            ),
            array(
                '<br><br>',
                '<br>',
                '',
                '&nbsp;&nbsp;',
                '&nbsp;&nbsp;',
                '$6' // Unlink all links in message content!
            ),
        $_POST['message']);
        $message = htmlentities($message, ENT_QUOTES, 'UTF-8'); // [2]
    } else {
        $error .= "<p class=\"message-error\">" . $messages['message_missing'] . "</p>";
    }

    // Check the math challenge answer to prevent spam robot.
    if( ! isset($_POST['math']) || empty($_POST['math']) || $_POST['math'] != $_SESSION['math']) {
        $error .= "<p class=\"message-error\">" . $messages['math_invalid'] . "</p>";
    }

    // Check for character length limit
    if(strlen($name) > $max_length_name) $error .= "<p class=\"message-error\">" . $messages['max_length_name'] . "</p>";
    if(strlen($url) > $max_length_url) $error .= "<p class=\"message-error\">" . $messages['max_length_url'] . "</p>";
    if(strlen($message) > $max_length_message) $error .= "<p class=\"message-error\">" . $messages['max_length_message'] . "</p>";

    // If all data entered by guest is valid, insert new data!
    if($error === "") {
        $new_data = $name . "\n" . $url . "\n" . $message . "\n" . $timestamp;
        if( ! empty($old_data)) {
            create_or_update_file($database . '.txt', $new_data . "\n\n==\n" . $old_data); // Prepend data
        } else {
            create_or_update_file($database . '.txt', $new_data); // Insert data
        }
    } else {
        // else, print the error messages.
        echo $error;
    }

}

// [3]
$_SESSION['guest_name'] = isset($_POST['name']) ? $_POST['name'] : "";
$_SESSION['guest_url'] = isset($_POST['url']) ? $_POST['url'] : "http://";
$_SESSION['guest_message'] = isset($_POST['message']) && $error != "" ? htmlentities($_POST['message'], ENT_QUOTES, 'UTF-8') : "";

// ----------------------------------------------------------------------------------------
// [1]. Prevent guest to type too many line break symbols.
// People usually do these thing to make their SPAM messages looks striking.
// [2]. Convert all HTML tags into HTML entities. This is done thoroughly for safety.
// We can revert back the escaped HTML into normal HTML tags later via `filter_html()`
// [3]. Save the form data into session. So if something goes wrong, the data entered
// by guest will still be stored in the form after submitting.
// ----------------------------------------------------------------------------------------


// Math challenge to prevent spam robot. 
// Current answer will be stored in `$_SESSION['math']`
$x = mt_rand(1, 10);
$y = mt_rand(1, 10);
if($x - $y > 0) {
    $math = $x . ' - ' . $y;
    $_SESSION['math'] = $x - $y;
} else {
    $math = $x . ' + ' . $y;
    $_SESSION['math'] = $x + $y;
}

// Testing...
// echo $math . ' = ' . $_SESSION['math'];


/**
 * Show the existing data.
 */
$data = file_get_contents($database . '.txt');
$current_page = isset($_GET['page']) ? $_GET['page'] : 1;
$nav = "";

if( ! empty($data)) {

    $data = explode("\n\n==\n", $data);
    $total_pages = ceil(count($data) / $per_page);

    // Create navigation if the number of pages is more than 1.
    if($total_pages > 1) {
        for($i = 0; $i < $total_pages; $i++) {
            if($current_page == ($i + 1)) {
                $nav .= " <span>" . ($i + 1) . "</span>"; // Disabled navigation
            } else {
                $nav .= " <a href=\"?page=" . ($i + 1) . (isset($_GET['data']) ? '&amp;data=' . $database : '') . "\">" . ($i + 1) . "</a>";
            }
        }
    }

    for($i = 0; $i < count($data); $i++) {
        $item = explode("\n", $data[$i]);
        // Permalink (single item)
        // http://localhost/guestbook/index.php&data=database-001&guest=0123456789
        if(isset($_GET['guest']) && preg_match('/[0-9]+/', $_GET['guest'])) {
            if($item[3] == $_GET['guest']) {
                echo "        <div class=\"guest-item\" id=\"guest-" . $item[3] . "\">\n";
                echo "          <strong class=\"guest-name\">";
                echo $item[1] == "-" ? "" : "<a href=\"" . $item[1] . "\" rel=\"nofollow\" target=\"_blank\">";
                echo $item[0];
                echo $item[1] == "-" ? "" : "</a>";
                echo "</strong>\n";
                echo "          <span class=\"guest-timestamp\">";
                echo "<time datetime=\"" . date('c', $item[3]) . "\">" . date('Y/m/d H:i', $item[3]) . "</time>";
                echo " <a href=\"?data=" . $database . "&amp;guest=" . $item[3] . "\" title=\"Permanent Link\">#</a>";
                echo "</span>\n";
                echo "          <span class=\"guest-message\">" . filter_html($item[2]) . "</span>\n";
                echo "        </div>\n";
            }
        // Normal list
        } else {
            if($i <= ($per_page * $current_page) - 1 && $i > ($per_page * ($current_page - 1)) - 1) {
                echo "        <div class=\"guest-item\" id=\"guest-" . $item[3] . "\">\n";
                echo "          <strong class=\"guest-name\">";
                echo $item[1] == "-" ? "" : "<a href=\"" . $item[1] . "\" rel=\"nofollow\" target=\"_blank\">";
                echo $item[0];
                echo $item[1] == "-" ? "" : "</a>";
                echo "</strong>\n";
                echo "          <span class=\"guest-timestamp\">";
                echo "<time datetime=\"" . date('c', $item[3]) . "\">" . date('Y/m/d H:i', $item[3]) . "</time>";
                echo " <a href=\"?data=" . $database . "&amp;guest=" . $item[3] . "\" title=\"Permanent Link\">#</a>";
                echo "</span>\n";
                echo "          <span class=\"guest-message\">" . filter_html($item[2]) . "</span>\n";
                echo "        </div>\n";
            }
        }
    }

} else {
    echo "        <div class=\"guest-item\">\n";
    echo "          <strong class=\"guest-name\">Guestbook</strong>\n";
    echo "          <span class=\"guest-message\">" . $messages['no_content'] . "</span>\n";
    echo "        </div>\n";
}

?>
      </div>
      <div class="guestbook-nav"><?php echo trim($nav); ?></div>
<?php if( ! isset($_GET['data']) && ! isset($_GET['guest'])): ?>
      <form method="post">
        <label>Name</label>
        <div><input type="text" name="name" value="<?php echo isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : ''; ?>"></div>
        <label>URL</label>
        <div><input type="url" name="url" value="<?php echo isset($_SESSION['guest_url']) ? $_SESSION['guest_url'] : 'http://'; ?>"></div>
        <label>Message</label>
        <div><textarea name="message"><?php echo isset($_SESSION['guest_message']) ? $_SESSION['guest_message'] : ''; ?></textarea></div>
        <hr>
        <div><?php echo $math; ?> = <input type="text" name="math" autocomplete="off"> <button type="submit">Send Message</button></div>
        <span class="clear"></span>
      </form>
<?php endif; ?>
    </div>

  </body>
</html>