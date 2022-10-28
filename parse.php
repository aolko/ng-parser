<?php

include_once 'vendor/autoload.php';
use simplehtmldom\HtmlWeb;
use simplehtmldom\HtmlDocument;

$database = new Dibi\Connection([
    'driver'        => 'sqlite',
    'database'      => 'ngdb.db',
    'profiler'      => [
        'file'      => 'sql.log'
    ]
]);

$client = new HtmlWeb();
$client_file = new HtmlDocument();

function htmlt($content="",$message=["type"=>"","body"=>""]){
    if (isset($message["body"]) && !empty($message["body"])) {
        if (isset($message['icon']) && !empty($message['icon'])){
            $message['icon'] = match ($message['icon']) {
                "info" => "<span class='iconify' data-icon='material-symbols:info-outline-rounded'></span>",
                "warning" => "<span class='iconify' data-icon='material-symbols:warning-outline-rounded'></span>",
                "danger" => "<span class='iconify' data-icon='material-symbols:dangerous-outline-rounded'></span>",
                "success" => "<span class='iconify' data-icon='material-symbols:check-circle-outline-rounded'></span>",
                default => null,
            };
        }
        $message = "<div class='message ".$message['type']."'><div class='icon'>".$message['icon']."</div><div class='body'>".$message['body']."</div></div>";
    } else {
        $message = null;
    }
    $html = <<<HTML
        <!doctype html>
        <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport"
                      content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                <meta http-equiv="X-UA-Compatible" content="ie=edge">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
                <script src="https://code.iconify.design/3/3.0.0/iconify.min.js"></script>
                <style>
                    .message{display:flex;align-items:flex-start;gap:10px;padding:20px;border-radius:8px;background:white;color:black;}
                    .message .icon{flex:0 0 32px;display:flex;align-items:center;justify-content:center;content:"";width:32px;height:32px;font-size:32px;}
                    .message .body{flex:1;}
                    .message .body>*:not(:last-child){margin: 0 0 10px 0;}
                    .message .body>*:last-child{margin: 0;}
                    .message .body h1,h2,h3,h4,h5{color:unset;}
                    .message .body a{text-decoration:unset;color:black;}
                    .message.info{background:#2196f3;color:black;}
                    .message.warning{background:#ffc107;color:black;}
                    .message.danger{background:#f44336;color:black;}
                    .message.success{background:#4caf50;color:black;}
                </style>
            </head>
            <body>
                $message
                $content
            </body>
        </html>
        
    HTML;
    return $html;
}

/*function getPaginated($username, $type){
    global $database;
    $getUserID = $database->fetch('SELECT id,username FROM users WHERE username = ?',$_GET['u'])['id'];

    for($i = getPages($_GET['u'],"news")[0]; $i<getPages($_GET['u'],"news")[-1]; $i++){
        //echo "<h2>Page {$i}</h2><hr>";
        foreach(getNews($_GET['u'],$i) as $item){
            $database->query('INSERT OR REPLACE INTO news', [
                'id' => null,
                'user_id' => $getUserID,
                'title' => strip_tags($item['title']),
                'text' => $item['details'],
                'page_num' => $i
            ]);
        };

    }
}*/

function getCurl($url,$json=0){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    //echo 'HTTP Status Code: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
    curl_close($ch);
    if ($json == 0){
        return $response;
    } else {
        return json_decode($response,true);
    }
}

function buildUrl($username,$section,$page=1,$json=0){
    if ($json == 0){
        return "https://{$username}.newgrounds.com/{$section}/{$page}";
    } else {
        return "https://{$username}.newgrounds.com/{$section}?isAjaxRequest=1&page={$page}";
    }

}

function getPages($username,$section){
    global $client;
    $url = "https://{$username}.newgrounds.com/{$section}";
    $html = $client->load($url);
    $pagination = $html->find(".userbody .userbody-guts .column.wide .pod", 0);
    $firstPage = $pagination->find(".pagenav > div > a > span",0)->plaintext;
    $lastPage = $pagination->find(".pagenav > div > a > span",-1)->plaintext;
    $pages = [0=>$firstPage,-1=>$lastPage];
    return $pages;
}

function getNews($username,$page=1){
    global $client;
    $url = "https://{$username}.newgrounds.com/news/{$page}";
    $html = $client->load($url);
    $articles = $html->find(".userbody .userbody-guts .column.wide .pod");
    unset($articles[0]);
    unset($articles[11]);
    foreach ($articles as $article){
        $item['title'] = $article->find('.pod-head > h2 > a', 0)->plaintext;
        $item['details'] = $article->find('.pod-body > .text-content > .ql-body', 0)->innertext;
        $item['details'] = str_replace("data-smartload-src","src",$item['details']);
        $items[] = $item;
    }
    return $items;
}

function getMovies($username,$page=1){
    global $client_file;
    $items = [];
    do {
        $items_tmp[] = getCurl(url:buildUrl($username,"movies",$page,1),json: 1)['items'];
        foreach($items_tmp as $k1 => $v1){
            foreach ($v1 as $k2 => $v2){
                foreach($v2 as $k3 => $v3){
                    //print_r($v3);
                   $html = $client_file->load($v3);
                    $url = $html->find("a.inline-card-portalsubmission",0)->href;
                    $title = $html->find("h4",0)->plaintext;
                    $cover = $html->find(".card-img",0)->src;
                    $score = $html->find("div.score-frame>div.star-score",0)->title;
                    $items[] = [
                        //"_page" => $page,
                        //"_html" => $v3,
                        "year" => $k2,
                        "url" => $url,
                        "title" => $title,
                        "cover" => $cover,
                        "score" => $score
                    ];


                }
            }
        }
        $page++;
    } while (!empty(getCurl(url:buildUrl($username,"movies",$page,1),json: 1)['items']));
    $items = array_unique($items,SORT_REGULAR);
    return $items;
}

function getArt($username,$page=1){
    global $client_file;
    $items = [];
    do {
        $items_tmp[] = getCurl(url:buildUrl($username,"art",$page,1),json: 1)['items'];
        foreach($items_tmp as $k1 => $v1){
            foreach ($v1 as $k2 => $v2){
                foreach($v2 as $k3 => $v3){
                    //print_r($v3);
                    $html = $client_file->load($v3);
                    $url = $html->find("a.item-portalitem-art-medium",0)->href;
                    $title = $html->find("h4",0)->plaintext;
                    $cover = $html->find(".item-icon>img",0)->src;
                    $score = null;
                    $items[] = [
                        //"_page" => $page,
                        //"_html" => $v3,
                        "year" => $k2,
                        "url" => $url,
                        "title" => $title,
                        "cover" => $cover,
                        "score" => $score
                    ];


                }
            }
        }
        $page++;
    } while (!empty(getCurl(url:buildUrl($username,"art",$page,1),json: 1)['items']));
    $items = array_unique($items,SORT_REGULAR);
    return $items;
}

if(isset($_GET) && !empty($_GET)) {
    $mode = $_GET['mode'];
    $username = $_GET['u'];
    if (isset($mode) && !empty($mode)) {
        if (isset($username) && !empty($username)){
            $getUserID = $database->fetch('SELECT id,username FROM users WHERE username = ?',$username)['id'];
            switch ($mode) {
                case "news":
                    echo htmlt("",["type"=>"info","icon"=>"info","body"=>"
                                <h2>Please wait</h2>
                                <p>News are currently being parsed...</p>
                            </div>
                        </div>
                    "]);
                    sleep(5);
                    for($i = getPages($username,$mode)[0]; $i<getPages($username,$mode)[-1]; $i++){
                        foreach(getNews($username,$i) as $item){
                            $database->query('INSERT OR REPLACE INTO news', [
                                'id' => null,
                                'user_id' => $getUserID,
                                'title' => strip_tags($item['title']),
                                'text' => $item['details'],
                                'page_num' => $i
                            ]);
                        };
                    }
                    break;
                case "art":
                    echo htmlt("",["type"=>"info","icon"=>"info","body"=>"
                                <h2>Please wait</h2>
                                <p>Art is currently being parsed...</p>
                            </div>
                        </div>
                    "]);
                    sleep(10);
                    //var_dump(getArt($username));
                    foreach (getArt($username) as $item){
                        $database->query('INSERT OR REPLACE INTO art', [
                            'id' => null,
                            'user_id' => $getUserID,
                            'title' => strip_tags($item['title']),
                            'cover' => $item['cover'],
                            'art_url' => $item['url'],
                            'year' => $item['year'],
                            'score' => $item['score']
                        ]);
                    }
                    break;
                case "movies":
                    echo htmlt("",["type"=>"info","icon"=>"info","body"=>"
                                <h2>Please wait</h2>
                                <p>Movies are currently being parsed...</p>
                            </div>
                        </div>
                    "]);
                    //print_r(getMovies($username));
                    foreach (getMovies($username) as $item){
                        $database->query('INSERT OR REPLACE INTO movies', [
                            'id' => null,
                            'user_id' => $getUserID,
                            'title' => strip_tags($item['title']),
                            'cover' => $item['cover'],
                            'movie_url' => $item['url'],
                            'year' => $item['year'],
                            'score' => $item['score']
                        ]);
                    }
                    break;
                default:
                    die(htmlt("",["type"=>"warning","icon"=>"warning","body"=>"
                        <h2>Whoops</h2>
                        <p>This script requires a mode to work</p>
                    "]));
                    break;
            }
        } else {
            die(htmlt("",["type"=>"warning","icon"=>"warning","body"=>"
                <h2>Whoops</h2>
                <p>You have to pick a username</p>
            "]));
        }
    } else {
        die(htmlt("",["type"=>"warning","icon"=>"warning","body"=>"
            <h2>Whoops</h2>
            <p>This script requires a mode to work</p>
        "]));
    }
} else {
    $html = htmlt("
        <h1 style='display:flex;align-items:center;gap:10px;'><img src='https://www.google.com/s2/favicons?sz=32&domain=newgrounds.com' alt='favicon'> NG Parser</h1>
        <small>by aolko</small>
        <h2>Available modes</h2>
        <ul>
            <li><strong>news</strong> - Scrape the news by <code>username</code></li>
            <li><strong>art</strong> - Scrape the art by <code>username</code></li>
            <li><strong>movies</strong> - Scrape the movies by <code>username</code></li>
        </ul>
        <h2>How to use</h2>
        <p>Use the <code>?mode=</code> mode and <code>&u=</code> username to scrape the content and add it to the database</p>
        <h2>FAQ</h2>
        <details>
            <summary>What if i save user's content twice?</summary>
            <p>Don't worry, it will be updated in the database</p>
        </details>
        <details>
            <summary>Where is the database located?</summary>
            <p>It's located in the script's folder as <code>ngdb.db</code></p>
        </details>
    ",["type"=>"warning","icon"=>"warning","body"=>"
        <h2>Whoops</h2>
        <p>This script requires a mode to work</p>
        "]);

    die($html);
}