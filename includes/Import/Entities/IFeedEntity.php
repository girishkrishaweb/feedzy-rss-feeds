<?php


namespace Feedzy_Rss_Feeds\Import\Entities;


interface IFeedEntity
{
    /** Input args left to maintain backwards compatibility with the hooks */
    public function get_items($start, $end);
    
    public function get_permalink();
    
    public function get_title();
}