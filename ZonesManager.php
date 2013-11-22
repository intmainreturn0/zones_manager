<?

namespace ZonesManager;

class ConfigLine
{
    public $Comment = null;
    public $CommentStart = false;
    /** @var ParsedItem */
    public $Item;

    function __construct( $item, $comment = null, $commentStart = false )
    {
        $this->Item         = $item;
        $this->Comment      = $comment;
        $this->CommentStart = $commentStart;
    }

    function __toString()
    {
        if( !$this->Comment )
            return $this->Item->__toString();
        return str_pad( $this->Item->__toString(), $this->CommentStart, ' ', STR_PAD_RIGHT ) . $this->Comment;
    }
}

class ConfigFile
{
    /** @var ConfigLine[] */
    public $Lines = [ ];

    public function AddLine( $line )
    {
        $this->Lines[] = $line;
    }

    function __toString()
    {
        return join( "\n", array_map( function ( $v )
        {
            /** @var $v ConfigLine */
            return $v->__toString();
        }, $this->Lines ) );
    }
}

abstract class ParsedItem
{
    abstract function __toString();
}

class UnknownText extends ParsedItem
{
    public $Text;

    public function __construct( $text )
    {
        $this->Text = $text;
    }

    function __toString()
    {
        return (string)$this->Text;
    }
}

class FileParser
{
    /**
     * Convert string lines (raw config) to array of parsed lines (ConfigFile)
     * @param string[] $lines
     * @return ConfigFile
     */
    public function ParseLines( $lines )
    {
        $file = new ConfigFile();
        foreach( $lines as $line )
        {
            $line           = trim( $line );
            $c_start        = strpos( $line, ';' );
            $comment        = $c_start !== false ? substr( $line, $c_start ) : null;
            $before_comment = $c_start !== false ? rtrim( substr( $line, 0, $c_start ) ) : $line;
            $item           = new UnknownText( $before_comment );
            $file->AddLine( new ConfigLine( $item, $comment, $c_start ) );
        }
        return $file;
    }
}

class ZonesManager
{
    /** @var ConfigFile */
    private $_file;

    function __toString()
    {
        return $this->_file->__toString();
    }

    static public function FromFile( $fileName )
    {
        return self::FromString( file_get_contents( $fileName ) );
    }

    static public function FromString( $str )
    {
        $zm        = new ZonesManager();
        $zm->_file = ( new FileParser() )->ParseLines( explode( "\n", $str ) );
        return $zm;
    }
}