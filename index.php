<?php

class PatreonRSSDownload {

    // todo
    // download art
    // accept XML address
    // set artist
    // set album
    private $image;
    private $artist = 'Gabrus, Rodgers, and Stanger';
    private $album = 'Action Boyz';
    private $channel;
    private $items;

    function __construct($file) {
        $this->file = $file;
    }

    public function readFile() {
        $xml = simplexml_load_file($this->file);
        $this->channel = $xml->rss->channel;
    }

    public function getImage() {
        $image = $this->channel->image->url;
    }

    public function dump($item) {
        echo '<pre>';
        var_dump($item);
        echo '</pre>';
    }

    public static function date_compare($a, $b) {
        $t1 = strtotime($a['pubDate']);
        $t2 = strtotime($b['pubDate']);
        return $t1 - $t2;
    }

    public function sortByDateAsc($items) {
        $array = $items;
        usort($array, array('PatreonRSSDownload', 'date_compare'));
        return $array;
    }

    public function getChannelItemsFormatted() {
        $items = array();
        error_log('getting formatted items');

        foreach($this->channel->item as $item) {
            $atts = $item->enclosure->attributes();

            $items[] = array(
                'title' => (string) $item->title,
                'url' => (string) $atts['url'],
                'type' => (string) $atts['type'],
                'description' => (string) $item->description,
                'pubDate' => (string) $item->pubDate,
                'image' => urldecode($this->image),
            );
        }

        $this->items = $this->sortByDateAsc($items);
    }

    public function sanitizeTitle($title) {
        return preg_replace('/[^A-Za-z0-9_\-\(\)\&]/', '_', $title);
    }

    public function downloadAllItems() {
        foreach ($this->items as $index => $item) {
            $file = $this->sanitizeTitle($item['title']);

            $source = "original/$file.mp3";
            $destination = "final/$file.m4a";

            if (file_exists($source) || file_exists($destination)) {
                error_log("Episode already downloaded: $source");
                continue;
            }

            error_log("Downloading episode: {$item['title']} - ( $source )");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $item['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec ($ch);
            curl_close ($ch);

            $source_destination = $source;
            $file = fopen($source_destination, "w+");
            fputs($file, $data);
            fclose($file);
        }
    }


    public function setFileMetadata() {
        error_log('setting metadata');

        foreach ($this->items as $index => $item) {
            $file = $this->sanitizeTitle($item['title']);

            $source = "original/$file.mp3";
            $destination = "final/$file.mp3";

            $num = $index + 1;
            $title = "{$num}: {$item['title']}";
            $description = (string )$item['description'];
            $time = strtotime($item['pubDate']);
            $date = date('Y-m-d',$time);
            $nnum = date('Ymd',$time);

            if (file_exists($source) && !file_exists($destination)) {
                echo "$title exists<br/>";
                // convert to m4a - has no cover art but has comments
                // exec("ffmpeg \
                //     -i \"$source\" \
                //     -i boyz.png -map 0:0 -map 1:0 -c copy -id3v2_version 3 -metadata:s:v title=\"Album cover\" -metadata:s:v comment=\"Cover (front)\" \
                //     -metadata track=\"$nnum\" \
                //     -metadata title=\"$title\" \
                //     -metadata comment=\"thisi is a test\" \
                //     -metadata artist=\"$artist\" \
                //     -metadata album=\"$album\" \
                //     -c:a aac -vn \"$destination\"");


                // convert to MP3 - has cover art but has no comments
                exec("ffmpeg \
                    -i \"$source\" \
                    -i boyz.png -map 0:0 -map 1:0 -c copy -id3v2_version 3 -metadata:s:v title=\"Album cover\" -metadata:s:v comment=\"Cover (front)\" \
                    -metadata track=\"$nnum\" \
                    -metadata title=\"$title\" \
                    -metadata artist=\"$artist\" \
                    -metadata album=\"$album\" \
                    \"$destination\"");

            } else {
                echo "ERROR: Original file '$file' doesn't exist<br/>";

            }
        }
    }

}
$xml = 'ActionBoyz.xml';
$rss = new PatreonRSSDownload($xml);
$rss->readFile();
$rss->getImage();
$rss->getChannelItemsFormatted();
// $rss->downloadAllItems();
$rss->setFileMetadata();
