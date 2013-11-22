<?

namespace ZonesManager;

class ConfigLine
{
    public $Comment = null;
    public $CommentStart = false;
    /** @var ParsedItem */
    public $Item;

    /**
     * @param ParsedItem  $item
     * @param string|null $comment
     * @param int|bool    $commentStart
     */
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

    /**
     * @return ParsedItem|null
     */
    public function LastItem()
    {
        for( $i = count( $this->Lines ) - 1; $i >= 0; --$i )
            if( !( $this->Lines[$i]->Item instanceof EmptyLine ) )
                return $this->Lines[$i]->Item;
        return null;
    }

    /**
     * @param string $parsedItemClassName
     * @return bool
     */
    public function IsLastItemOfType( $parsedItemClassName )
    {
        $last = $this->LastItem();
        return $last && get_class( $last ) === __NAMESPACE__ . '\\' . $parsedItemClassName;
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

    /**
     * Checks if current line is of concrete type
     * @param string     $c Actual content of raw config line (without comment)
     * @param ConfigFile $file
     * @return bool
     * @abstract
     */
    static public function IsMy( $c, $file )
    {
        /* abstract, has to be overridden (static methods can't be marked abstract in php) */
    }
}

class UnknownContent extends ParsedItem
{
    public $Content;

    public function __construct( $content )
    {
        $this->Content = $content;
    }

    function __toString()
    {
        return (string)$this->Content;
    }

    static public function IsMy( $c, $file )
    {
        return true;
    }
}

class EmptyLine extends ParsedItem
{
    function __toString()
    {
        return '';
    }

    static public function IsMy( $c, $file )
    {
        return rtrim( $c ) === '';
    }
}

class Origin extends ParsedItem
{
    public $Value;

    public function __construct( $content )
    {
        $this->Value = ltrim( substr( $content, 8 ) ); // after '$ORIGIN '
    }

    function __toString()
    {
        return '$ORIGIN ' . $this->Value;
    }

    static public function IsMy( $c, $file )
    {
        return $c[0] === '$' && substr( $c, 0, 7 ) === '$ORIGIN' && ctype_space( $c[7] );
    }
}

class SOAStart extends ParsedItem
{
    public $Domain, $Ns, $EMail;

    public function __construct( $content )
    {
        list( $this->Domain, , , $this->Ns, $this->EMail ) = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
    }

    function __toString()
    {
        return sprintf( '%s   IN    SOA   %s %s (', $this->Domain, $this->Ns, $this->EMail );
    }

    static public function IsMy( $c, $file )
    {
        return preg_match( '/\sIN\s+SOA\s/', $c ) && strrpos( $c, '(' );
    }
}

class SOASerial extends ParsedItem
{
    public $Number;

    public function __construct( $content )
    {
        $this->Number = trim( $content );
    }

    function __toString()
    {
        return sprintf( '%10s%s', '', $this->Number );
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOAStart' ) && ctype_alnum( trim( $c ) );
    }
}

class TTL extends ParsedItem
{
    public $Value;

    public function __construct( $text )
    {
        $this->Value = ltrim( substr( $text, 5 ) ); // after '$TTL '
    }

    function __toString()
    {
        return '$TTL ' . $this->Value;
    }

    static public function IsMy( $c, $file )
    {
        return $c[0] === '$' && substr( $c, 0, 4 ) === '$TTL' && ctype_space( $c[4] );
    }
}

class FileParser
{
    private $_file;

    /**
     * Convert string lines (raw config) to array of parsed lines (ConfigFile)
     * @param string[] $lines
     * @return ConfigFile
     */
    public function ParseLines( $lines )
    {
        $this->_file = new ConfigFile();
        foreach( $lines as $line )
        {
            $line = rtrim( $line );
            $this->_file->AddLine( $this->_ParseLine( $line ) );
        }
        return $this->_file;
    }

    /**
     * Parse single line of raw config - separate comment from actual content (and parse it).
     * @param string $line Line of raw config
     * @return ConfigLine
     */
    private function _ParseLine( $line )
    {
        $c_start        = strpos( $line, ';' );
        $comment        = $c_start !== false ? substr( $line, $c_start ) : null; // comment itself (contains ';' and all next)
        $actual_content = $c_start !== false ? rtrim( substr( $line, 0, $c_start ) ) : $line; // before comment
        return new ConfigLine( $this->_ParseActualContent( $actual_content ), $comment, $c_start );
    }

    /**
     * Parse actual content - a line cleaned from comment
     * @param string $c Content to parse
     * @return ParsedItem
     */
    private function _ParseActualContent( $c )
    {
        static $classes = [ 'TTL', 'Origin', 'SOAStart', 'EmptyLine', 'SOASerial' ];
        $dest_class = 'UnknownContent';
        foreach( $classes as $c_name )
            if( call_user_func( "\\" . __NAMESPACE__ . "\\$c_name::IsMy", $c, $this->_file ) )
            {
                $dest_class = $c_name;
                break;
            }
        $dest_class = "\\" . __NAMESPACE__ . "\\$dest_class";
        return new $dest_class( $c );
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

    function DebugString()
    {
        $s = '';
        foreach( $this->_file->Lines as $line )
        {
            $class = get_class( $line->Item ); // class with namespace
            $class = substr( $class, strrpos( $class, '\\' ) + 1 );
            $s .= ';;;;;;; ' . $class . "\n" . $line->__toString() . "\n\n";
        }
        return $s;
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