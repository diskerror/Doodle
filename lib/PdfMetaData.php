<?php


use Diskerror\Typed\Scalar\TString;
use Diskerror\Typed\Scalar\TStringTrim;
use Diskerror\Typed\TypedClass;

class PdfMetaData extends TypedClass
{
	protected string $filename;
	protected TStringTrim $title;
	protected TStringTrim $author;			//	Composer, One or more comma-separated values
	protected TStringTrim $subject;			//	Genres, One or more comma-separated values
	protected TStringTrim $keywords;		//	Tags, One or more comma-separated values
	protected ?int $rating = null;			//	Rating	Whole number between 0 and 5
	protected ?int $difficulty = null;		//	Difficulty	Whole number between 0 and 3
	protected ?int $duration = null;		//	Duration	Non-negative whole number (in seconds)
	protected ?int $keysf = null;			//	Key	A whole number between -7 and 7
	protected ?int $keymi = null;			//	Key	0 (major) or 1 (minor)
}
