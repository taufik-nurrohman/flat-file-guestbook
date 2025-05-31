<?php session_start();

// =================================================================================
// PHP FLAT-FILE GUESTBOOK by Taufik Nurrohman <https://github.com/taufik-nurrohman>
// =================================================================================

// START CONFIGURATION
$database = 'database-001'; // Your TXT file name as database.
$per_page = 5; // The number of items you want to display per page.
$time_zone = 'Asia/Jakarta'; // Look at `date_default_timezone_set()`
$max_length_name = 60; // Maximum character length for guest name
$max_length_url = 120; // Maximum character length for guest URL
$max_length_message = 1000; // Maximum character length for guest message
$messages = array(
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
if (isset($_GET['data'])) {
    $database = basename($_GET['data']);
}

$error = ""; // error messages

// Check whether the "database" is not available. If not, create one!
if (!file_exists($database . '.txt')) {
    // Prevent guest to create new database via `data=database-XXX` in URL
    // Only administrator can do this by editing the `$database` value
    if (!isset($_GET['data'])) {
        create_or_update_file($database . '.txt', "");
    }
} else {
    $old_data = file_get_contents($database . '.txt');
}

/**
 * Post a message
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = "";
    $url = "";
    $message = "";
    $timestamp = date('U');

    // Make sure the guest name is not empty.
    if (isset($_POST['name']) && !empty($_POST['name'])) {
        $name = strip_tags($_POST['name']);
    } else {
        $error .= "<p class=\"message-error\">" . $messages['name_missing'] . "</p>";
    }

    // Make sure the URL format is valid. Set its value as `-` if empty.
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        if (filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
            $url = strip_tags($_POST['url']);
        } else {
            $error .= "<p class=\"message-error\">" . $messages['url_invalid'] . "</p>";
        }
    } else {
        $url = "-";
    }

    // Make sure the guest message is not empty.
    if (isset($_POST['message']) && !empty($_POST['message'])) {
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
        $message = htmlspecialchars($message, ENT_QUOTES); // [2]
    } else {
        $error .= "<p class=\"message-error\">" . $messages['message_missing'] . "</p>";
    }

    // Check the math challenge answer to prevent spam robot.
    if (!isset($_POST['math']) || empty($_POST['math']) || $_POST['math'] != $_SESSION['math']) {
        $error .= "<p class=\"message-error\">" . $messages['math_invalid'] . "</p>";
    }

    // Check for character length limit
    if (strlen($name) > $max_length_name) $error .= "<p class=\"message-error\">" . $messages['max_length_name'] . "</p>";
    if (strlen($url) > $max_length_url) $error .= "<p class=\"message-error\">" . $messages['max_length_url'] . "</p>";
    if (strlen($message) > $max_length_message) $error .= "<p class=\"message-error\">" . $messages['max_length_message'] . "</p>";

    // [3]
    $_SESSION['guest_name'] = isset($_POST['name']) ? $_POST['name'] : "";
    $_SESSION['guest_url'] = isset($_POST['url']) ? $_POST['url'] : "";
    $_SESSION['guest_message'] = isset($_POST['message']) && $error != "" ? htmlspecialchars($_POST['message'], ENT_QUOTES) : "";

    // If all data entered by guest is valid, insert new data!
    if (!$error) {
        $new_data = $name . "\n" . $url . "\n" . $message . "\n" . $timestamp;
        if (!empty($old_data)) {
            create_or_update_file($database . '.txt', $new_data . "\n\n==\n" . $old_data); // Prepend data
        } else {
            create_or_update_file($database . '.txt', $new_data); // Insert data
        }
        $hash = '#guest-' . $timestamp;
    } else {
        $_SESSION['error'] = $error;
        $hash = "";
    }

    header('location: ' . basename(__FILE__) . $hash);
    exit;

}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : "";
$guest_name = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : "";
$guest_url = isset($_SESSION['guest_url']) ? $_SESSION['guest_url'] : "";
$guest_message = isset($_SESSION['guest_message']) ? $_SESSION['guest_message'] : "";

// Clear sessions
unset($_SESSION['error'], $_SESSION['guest_name'], $_SESSION['guest_url'], $_SESSION['guest_message']);

// Math challenge to prevent spam robot.
// Current answer will be stored in `$_SESSION['math']`
$x = mt_rand(1, 10);
$y = mt_rand(1, 10);
if ($x - $y > 0) {
    $math = $x . ' - ' . $y;
    $_SESSION['math'] = $x - $y;
} else {
    $math = $x . ' + ' . $y;
    $_SESSION['math'] = $x + $y;
}

// Testing...
// echo $math . ' = ' . $_SESSION['math'];

// ----------------------------------------------------------------------------------------
// [1]. Prevent guest to type too many line break symbols.
// People usually do these thing to make their SPAM messages looks striking.
// [2]. Convert all HTML tags into HTML entities. This is done thoroughly for safety.
// We can revert back the escaped HTML into normal HTML tags later via `filter_html()`
// [3]. Save the form data into session. So if something goes wrong, the data entered
// by guest will still be stored in the form after submitting.
// ----------------------------------------------------------------------------------------

/**
 * Show the existing data.
 */
$data = file_get_contents($database . '.txt');
$current_page = isset($_GET['page']) ? $_GET['page'] : 1;
$nav = "";

echo "<!DOCTYPE html>\n";
echo "<html dir=\"ltr\">\n";
echo "  <head>\n";
echo "    <meta charset=\"utf-8\">\n";
echo "    <title>Flat-File Guestbook</title>\n";
echo "    <link href=\"index.css\" rel=\"stylesheet\">\n";
echo "  </head>\n";
echo "  <body>\n";
echo "    <div class=\"guestbook\" id=\"guestbook\">\n";
echo "      <div class=\"guest-messages\" id=\"guest-messages\">\n";

echo $error ? "        " . $error . "\n" : "";

if (!empty($data)) {

    $data = explode("\n\n==\n", $data);
    $total_pages = ceil(count($data) / $per_page);

    // Create navigation if the number of pages is more than 1.
    if ($total_pages > 1) {
        for ($i = 0; $i < $total_pages; $i++) {
            if ($current_page == ($i + 1)) {
                $nav .= " <span>" . ($i + 1) . "</span>"; // Disabled navigation
            } else {
                $nav .= " <a href=\"?page=" . ($i + 1) . (isset($_GET['data']) ? '&amp;data=' . $database : '') . "\">" . ($i + 1) . "</a>";
            }
        }
    }

    for ($i = 0; $i < count($data); $i++) {
        $item = explode("\n", $data[$i]);
        // Permalink (single item)
        // http://localhost/guestbook/index.php&data=database-001&guest=0123456789
        if (isset($_GET['guest']) && preg_match('/[0-9]+/', $_GET['guest'])) {
            if ($item[3] == $_GET['guest']) {
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
            if ($i <= ($per_page * $current_page) - 1 && $i > ($per_page * ($current_page - 1)) - 1) {
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

if (!isset($_GET['data']) && !isset($_GET['guest'])) {
    echo "      </div>\n";
    echo "      <div class=\"guestbook-nav\">" . trim($nav) . "</div>\n";
    echo "      <form method=\"post\">\n";
    echo "        <label>Name</label>\n";
    echo "        <div><input type=\"text\" name=\"name\" value=\"" . htmlspecialchars($guest_name) . "\"></div>\n";
    echo "        <label>URL</label>\n";
    echo "        <div><input type=\"url\" name=\"url\" placeholder=\"http://" . $_SERVER['HTTP_HOST'] . "\" value=\"" . htmlspecialchars($guest_url) . "\"></div>\n";
    echo "        <label>Message</label>\n";
    echo "        <div><textarea name=\"message\">" . htmlspecialchars($guest_message) . "</textarea></div>\n";
    echo "        <hr>\n";
    echo "        <div>" . $math . " = <input type=\"text\" name=\"math\" autocomplete=\"off\"> <button type=\"submit\">Send Message</button></div>\n";
    echo "        <span class=\"clear\"></span>\n";
    echo "      </form>\n";
} else {
    echo "      </div>\n";
}

echo "    </div>\n";
echo "  </body>\n";
echo "</html>";