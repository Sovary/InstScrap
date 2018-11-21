<?php
require_once("simple_html_dom.php");
/**
 * This class help extract data from Instagram site
 * @author Sovary Kong <hellokh.dev@gmail.com>
 * Created at Oct 24, 2018
 * Last Updated Nov 21, 2018
 */
class InstDownloader
{
    //data
    private $data_raw;
    private $html_dom;

    

    public function __construct(){}
    /**
     * prepare documents
     */
    public function getReadyData($url="")
    {
        if(empty($url) || $url == "") throw new ValidatingException("URL required");
        if (strpos($url, 'instagram') === false) throw new ValidatingException("URL is incorrected");
        $this->html_dom = $this->cache_get_contents($url);
        if(!isset($this->html_dom)) throw new ValidatingException("DOM unable to load");
        $pattern = '/window._sharedData = (.*);/';
        $sib =$this->html_dom
            ->find("#react-root",0)
            ->next_sibling();

        $inn = $sib->innertext;
        preg_match($pattern, $inn, $matches);
        if(count($matches)>1)
        {
            $this->data_raw = json_decode($matches[1]);
        }
    }

    private function cache_get_contents($url, $offset = 600) {
    
        $dir = 'cache';
        $file = $dir.'/cache_' . md5($url);
        if(!is_dir($dir)) mkdir($dir);
        $context = stream_context_create();
        stream_context_set_params($context, array(
        'user_agent' => 
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36'));
        if (file_exists($file) && filemtime($file) > time() - $offset)
             return file_get_html($file, 0,$context);
    
        $contents = file_get_html($url,0,$context);
        if ($contents === false)
            return false;
        file_put_contents($file, $contents);
        return $contents;
    }

    public function close()
    {
        $this->html_dom->clear();
        unset($this->html_dom);
        unset($this->data_raw);
    }

    /**
     * extract data from json
     */
    public function getDataFromJson()
    {
        if(!isset($this->data_raw)) throw new ValidatingException("Unreachable data js",1);
        $line = new InstScrapeLine();
        
        if(isset($this->data_raw->entry_data->ProfilePage)) throw new ValidatingException("Unable to handle on Private Account");

        $all= $this->data_raw
            ->entry_data
            ->PostPage[0]
            ->graphql
            ->shortcode_media;
        if(isset($all->edge_media_to_caption))
        {
            $line->caption = $all->edge_media_to_caption
                ->edges[0]
                ->node->text;
        }
        $line->tag = $this->getHashTags();
        $line->post_url = $this->getUrl();
        $user = $all->owner;
        $line->user_id = $user->id;
        $line->user_name = $user->username;
        $line->user_pic_url = $user->profile_pic_url;
        //album
        if(isset($all->edge_sidecar_to_children))
        {
            $line->items = [];
            foreach ($all->edge_sidecar_to_children->edges as $item) 
            {
                $subline = new InstScrapeSubline();

                $node = $item->node;
                $subline->is_video = $node->is_video;
                if($node->is_video)
                {
                    $subline->url_video = $node->video_url;
                }
                $subline->url_images = [];
                foreach($node->display_resources as $item)
                {
                    $img = [];
                    $img["src"] = $item->src;
                    $img["w"] = $item->config_width;
                    $img["h"] = $item->config_height;
                    $subline->url_images[] = $img; 
                }
                $line->items[] = $subline;
            }
            return $line;
        }

        //Only one
        $subline = new InstScrapeSubline();
        $subline->is_video = $all->is_video;
        if($all->is_video)
        {
            $subline->url_video = $all->video_url;
        }
        $subline->url_images = [];
        foreach($all->display_resources as $item)
        {
            $img = [];
            $img["src"] = $item->src;
            $img["w"] = $item->config_width;
            $img["h"] = $item->config_height;
            $subline->url_images[] = $img; 
        }
        $line->items[] = $subline;
        return $line;
    }

    /**
     * return url;
     */
    public function getUrl()
    {
        return $this->html_dom->find("meta[property='og:url']",0)->content;
    }

    /**
     * return hashtags
     */
    public function getHashTags()
    {
        $detail = [];
        foreach ($this->html_dom->find("meta[property*='tag']") as $item) 
        {
            $detail[] = $item->content;
        }
        return $detail;
    }
    /**
     * extract data from html tag
     */
    public function getDataFromTags()
    {
        if(!isset($this->html_dom)) throw new ValidatingException("Unreachable data DOM");
        $line = new InstScrapeLine();
        $line->is_dom = true;
        $eleVideo =$this->html_dom
            ->find("meta[property='og:video']",0);
        $eleImage = $this->html_dom
            ->find("meta[property='og:image']",0);
        $line->caption = $this->html_dom
            ->find("meta[property='og:title']",0)
            ->content;
        $line->tag = $this->getHashTags();
        $line->post_url = $this->getUrl();

        $subline = new InstScrapeSubline();

        $subline->is_video = isset($eleVideo);
        if($subline->is_video)
        {
            $subline->url_video = $eleVideo->content;  
        }
        $img = [];
        $img["src"] = $eleImage->content;
        $img["w"] = 0;
        $img["h"] = 0;
        $subline->url_images[] = $img; 
        $line->items[] = $subline;
        
        return $line;
    }

    public function getItemsInDetailPage()
    {
        $rs = [];
        try
        {
            $rs = $this->getDataFromJson();
        }
        catch(ValidatingException $e)
        {   
            if($e->getCode() === 1)
            {
                $rs = $this->getDataFromTags();
            }
            else
            {
                throw $e;
            }
            //var_dump($e);
        }
        finally{ $this->close();}
        return $rs;
    }
}
class ValidatingException extends Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, Exception $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
}
class InstScrapeLine
{
    public $is_dom = false;    
    public $user_id = "";
    public $user_name = "unknown";
    public $user_pic_url ="";
    public $caption = "";
    public $tag =[];
    public $post_url = "";
    //InstScrapeSubline
    public $items;
}
class InstScrapeSubline
{
    public $is_video = false;
    public $url_video = "";
    public $url_images = [];
}
?>