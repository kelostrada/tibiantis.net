<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database configuration
$hostname = $_ENV['DB_HOSTNAME']; // MySQL server hostname
$username = $_ENV['DB_USERNAME'];; // MySQL username
$password = $_ENV['DB_PASSWORD']; // MySQL password
$database = $_ENV['DB_DATABASE']; // MySQL database name

function exists($sha)
{
    global $hostname, $username, $password, $database;

    // Create a database connection
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query for SELECT statement
    $sql = "SELECT * FROM recordings WHERE sha = '$sha';";

    // Execute the query
    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    // Check if there are rows returned
    return $result->num_rows > 0;
}

function insert($filename, $sha, $uploader)
{
    global $hostname, $username, $password, $database;

    // Create a database connection using prepared statements
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query using a prepared statement
    $sql = "INSERT INTO recordings (filename, sha, uploader) VALUES (?, ?, ?)";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bind_param("sss", $filename, $sha, $uploader);

    // Execute the statement
    if ($stmt->execute()) {
        $result = true;
    } else {
        $result = "Problem uploading file.";
    }

    // Close the statement
    $stmt->close();

    // Close the database connection
    $conn->close();

    return $result;
}

function explorers()
{
    global $hostname, $username, $password, $database;

    // Create a database connection using prepared statements
    $conn = new mysqli($hostname, $username, $password, $database);

    // Check if the connection was successful
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT r.uploader, count(*) as amount, max(uut.count) as count
    FROM `recordings` r
    LEFT JOIN `uploader_unique_tiles` uut ON r.uploader = uut.uploader
    GROUP BY r.uploader
    order by count desc;";

    // Execute the query
    $result = $conn->query($sql);

    // Close the database connection
    $conn->close();

    return $result;
}

function store($file, $name, $uploader)
{
    $target_dir = "../recordings/";
    $sha = sha1_file($file);

    if (exists($sha))
        return "File $name was already uploaded.";

    $insert = insert($name, $sha, $uploader);

    if ($insert !== true)
        return $insert;

    if (!copy($file, "$target_dir$name-$sha")) {
        return "Problem copying the file to server.";
    }

    return true;
}

if (isset($_POST["submit"])) {
    $file_type = strtolower(pathinfo(basename($_FILES["file"]["name"]), PATHINFO_EXTENSION));

    if ($file_type == "cam") {
        $result = store($_FILES["file"]["tmp_name"], $_FILES["file"]["name"], $_POST["uploader"]);
    } else if ($file_type == "zip") {
        $zip_sha = sha1_file($_FILES["file"]["tmp_name"]);
        $archive_directory = sys_get_temp_dir() . "/recordings/$zip_sha";

        $zip_obj = new ZipArchive;
        $zip_obj->open($_FILES["file"]["tmp_name"]);
        $zip_obj->extractTo($archive_directory);

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($archive_directory));

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;

            }
            // starts_with . - ignore
            if (substr($file->getFilename(), 0, 1) === ".")
                continue;

            $result = true;

            if ($file->getExtension() == "cam") {
                $store_result = store($file->getPathname(), $file->getFilename(), $_POST["uploader"]);
                if ($store_result !== true) {

                    if ($result === true) {
                        $result = $store_result;
                    } else {
                        $result .= $store_result;
                    }
                }
            }
        }

    } else {
        $result = "Incorrect extension! Only .cam and .zip files are supported right now.";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Tibiantis Map Generator</title>
    <style>
        body {
            background-color: #000;
            color: #FFF;
            font-family: Arial, sans-serif;
        }

        h1 {
            color: #FFD700;
        }

        a {
            color: #FFD700;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #222;
            border: 2px solid #555;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #333;
        }

        tr:nth-child(even) {
            background-color: #444;
        }

        input[type="file"] {
            /* display: none; */
            /* margin-bottom: 10px; */
        }

        input[type="text"] {
            padding: 5px;
            margin: 16px 0;
        }

        .upload-btn {
            background-color: #00FF00;
            color: #000;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }

        .upload-btn:hover {
            background-color: #00CC00;
        }

        .submit-btn {
            background-color: #00FF00;
            color: #000;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }

        .submit-btn:hover {
            background-color: #00CC00;
        }

        p.success-message {
            color: green;
        }

        p.error-message {
            color: red;
        }
    </style>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BQ64D3FQQS"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());

        gtag('config', 'G-BQ64D3FQQS');
    </script>
</head>

<body>
    <div class="container">
        <?php
        if ($result && $result !== true) {
            echo "
                <p class=\"error-message\">
                    Error: $result
                </p>
                ";
        }

        if ($result === true) {
            echo "<p class=\"success-message\">Thanks for the upload!</p>";
        }
        ?>

        <h1>Tibiantis Map Generator</h1>
        <a href="https://tibiantis.net/map_viewer">Tibiantis Map</a>
        <p>Upload your Tibiantis recordings and help us convert them into a browseable map.</p>
        <p>You can upload one by one but it will be easier to just zip the whole cam directory inside Tibiantis
            installation and pass all the files at once this way.</p>
        <p>We will store the files only to generate the map, however obviously if you are worried about private things
            that you do in-game then by all means please don't upload these files or just upload selected files :)</p>

        <!-- File uploader -->
        <form id="upload-form" enctype="multipart/form-data" method="POST">
            <input type="file" id="file-upload" name="file" accept=".cam,.zip"><br />

            <label for="uploader">Introduce yourself:</label>
            <input type="text" id="uploader" name="uploader" /><br />

            <button type="submit" class="submit-btn" name="submit">Submit</button>
        </form>

        <br />
        <hr />

        <!-- Paginated table/list -->
        <table>
            <thead>
                <tr>
                    <th>Explorer</th>
                    <th>Unique Tiles</th>
                    <th>Uploaded recordings</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach (explorers() as $explorer) {
                ?>
                <tr>
                    <td><?php echo $explorer['uploader'] ?></td>
                    <td><?php echo $explorer['count'] ?></td>
                    <td><?php echo $explorer['amount'] ?></td>
                </tr>
                <?php
                }
                ?>
            </tbody>
        </table>

        <!-- Placeholder for explanation -->
        <div id="explanation">
            <h2>Short explanation about the scripts and process used</h2>
            <p>The process of generating the map is currently a semi-manual one.
                I have reused and prepared scripts that will help me with the process,
                but it's still far from being able to be called automatic. That's why the map
                will be updated every once in a while (whenever I find time to download all the
                uploaded cams and rerun the process). However the plan is to make it fully automatic.
                Right now the process is also very naive - which means that every item that you see or put
                on the ground might actually end up in the generated map image. This is becuase I am taking all
                the items from the cam files and put there on the generated map. However what I have planned
                is to actually store the data in some kind of database and decide based on timestamps / commonality
                if these removable items should be put on map. This way we will be able to analyze the items
                and generate almost identical map that Tibiantis has after server save. You can also notice
                the lack of spawns and a lot of dead bodies on the ground. Technically I could remove the bodies
                even now, but decided since we don't have any spawn data it might be actually helpful to see
                the dead bodies - it will give some rough idea about the creatures spawning in different areas.
            </p>
            <p>
                Whenever I start storing detailed data in my own database I plan to actually introduce
                a grading system - one that will tell us which characters contributed the most to
                exploring Tibiantis map :) I will sponsor some rewards too - probably a premium pig per month or so
                for people who are contributing the most files and uncovering the most tiles/sqms. People
                who were the first to uncover some places will have more points etc. This is however on my
                roadmap but since I will own all these files I will be able to calculate this post-factum
                and reward whoever will be due. Since this is how I intend it to work for everyone to enjoy
                I am obviously accepting any donates - all of them will go for the server payments if needed or
                the rewards for people to invite more people to upload cams.
            </p>

            <h2>Contact and donations:</h2>
            <ul>
                <li>Discord: <a href="https://discord.com/users/kelostrada">Kelu</a></li>
                <li>Paypal/Email: <a href="mailto:kelostrada@gmail.com">kelostrada@gmail.com</a></li>
                <li>Tibiantis: Map Donate / Thais</li>
            </ul>
            <h2>Used software:</h2>
            <ul>
                <li><a
                        href="https://github.com/kelostrada/TibiantisCAMConverter">https://github.com/kelostrada/TibiantisCAMConverter</a>
                </li>
                <li><a href="https://github.com/gesior/otclient_mapgen">https://github.com/gesior/otclient_mapgen</a>
                </li>
            </ul>
        </div>
    </div>
</body>

</html>