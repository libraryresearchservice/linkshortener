# linkshortener
Link shortening service

    // Include the library
    include YOUR_SITE_PATH.'/vendor/lrs/linkshortener/src/Lrs/Linkshortener/IntHasherInterface.php';
    include YOUR_SITE_PATH.'/vendor/lrs/linkshortener/src/Lrs/Linkshortener/Base36Hasher.php';
    include YOUR_SITE_PATH.'/vendor/lrs/linkshortener/src/Lrs/Linkshortener/LinkShortener.php';
    
    try {
    	// Create instance of PDO
    	$pdo = new PDO('mysql:host=YOUR_DB_HOST;dbname=YOUR_DB_NAME', 'YOUR_DB_USER', 'YOUR_DB_PASSWORD', array(
    		PDO::ATTR_ERRMODE 				    => PDO::ERRMODE_EXCEPTION, 
    		PDO::MYSQL_ATTR_INIT_COMMAND	=> "SET NAMES 'UTF8'"
    	));
    	// Pass instances of PDO and IntHasherInterface (or something that implements it) to LinkShortener
    	$l = new Lrs\Linkshortener\LinkShortener('https://your.link.shortener.com', $pdo, new Lrs\IntHasher\Base36Hasher);
      // Shortening a link produces a numeric ID that is converted/hashed/whatevered
    	$l->shorten('https://www.lrs.org'); // returns https://your.link.shortener.com/l12
    	// Lengthening a link is the reverse:  take an ID and unconver/hash/whatever it
    	$l->lengthen('l12') // returns https://www.lrs.org
    } catch (Exception $e) {
    	die('The link shortener is currently not available.');
    }
