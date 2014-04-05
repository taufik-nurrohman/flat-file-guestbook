Introduction
------------

![Screenshot](http://4.bp.blogspot.com/-gbPrAb7GAbM/UvWVwUgDm6I/AAAAAAAAIdI/STPaTQerxtg/s1600/flat-file-guestbook-with-php.png)

*Flat-File GuestBook* is a simple-single file guestbook application without database written in PHP. Instead, the user data will be stored into a text file.

Details
-------

Download and extract the file to get a `guestbook` folder. Copy the folder and it&rsquo;s contents then paste to your localhost folder.

Now open `http://localhost/guestbook/index.php` with your favorite browser. *Done!*

Configurations
--------------

``` .php
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
```