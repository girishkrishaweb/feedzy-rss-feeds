<?php


namespace Feedzy_Rss_Feeds\Import\Entities;


abstract class AbstractFeedEntity implements IFeedEntity
{
    protected $title;
    
    protected $items;
}