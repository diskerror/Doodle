<?php

namespace Music;

use Diskerror\Typed\Scalar\TStringTrim;
use Diskerror\Typed\TypedClass;


/**
 * Metadata for usage with the application forScore.
 */
class PdfMetaData extends TypedClass
{
    protected array $_map = [
        'File Name'=> 'filename',
        'Title'=> 'title',
        'Composer'=> 'author',
        'Ensemble'=> 'subject',
        'Keywords'=> 'keywords'
    ];

    protected TStringTrim $filename;
    protected TStringTrim $title;
    protected TStringTrim $author;      //	Composer, One or more comma & space-separated values
    protected TStringTrim $subject;     //	Genres or Ensemble, One or more comma & space-separated values
    protected TKeywords   $keywords;    //	Array to become a list of comma&space-separated values
}
