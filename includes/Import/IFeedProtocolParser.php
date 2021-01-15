<?php


namespace Feedzy_Rss_Feeds\Import;


interface IFeedProtocolParser
{
    // TODO: Return IFeedEntity
    public function fetch_feed( $feed_url, $cache = '12_hours', $sc );
}