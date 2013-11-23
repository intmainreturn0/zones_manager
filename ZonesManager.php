<?

namespace ZonesManager;

class ParseZoneException extends \Exception
{

}

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

class SOAStart extends ParsedItem
{
    const SUB_INDENT = '              ';

    public $Domain, $Ns, $EMail;

    public function __construct( $content )
    {
        list( $this->Domain, , , $this->Ns, $this->EMail ) = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
    }

    function __toString()
    {
        return sprintf( '%-' . ( strlen( self::SUB_INDENT ) - 1 ) . 's IN    SOA   %s %s (', $this->Domain, $this->Ns, $this->EMail );
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
        return SOAStart::SUB_INDENT . $this->Number;
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOAStart' ) && ctype_alnum( trim( $c ) );
    }
}

class SOARefresh extends ParsedItem
{
    public $Value;

    public function __construct( $content )
    {
        $this->Value = trim( $content );
    }

    function __toString()
    {
        return SOAStart::SUB_INDENT . $this->Value;
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOASerial' );
    }
}

class SOARetry extends ParsedItem
{
    public $Value;

    public function __construct( $content )
    {
        $this->Value = trim( $content );
    }

    function __toString()
    {
        return SOAStart::SUB_INDENT . $this->Value;
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOARefresh' );
    }
}

class SOAExpiry extends ParsedItem
{
    public $Value;

    public function __construct( $content )
    {
        $this->Value = trim( $content );
    }

    function __toString()
    {
        return SOAStart::SUB_INDENT . $this->Value;
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOARetry' );
    }
}

class SOACaching extends ParsedItem
{
    public $Value;
    public $IsClosing = false;

    public function __construct( $content )
    {
        $this->Value = trim( $content );
        if( substr( $this->Value, -1 ) === ')' )
        {
            $this->IsClosing = true;
            $this->Value     = rtrim( substr( $this->Value, 0, -1 ) );
        }
    }

    function __toString()
    {
        return SOAStart::SUB_INDENT . $this->Value . ( $this->IsClosing ? ' )' : '' );
    }

    static public function IsMy( $c, $file )
    {
        return $file->IsLastItemOfType( 'SOAExpiry' );
    }
}

class SOAEnd extends ParsedItem
{
    function __toString()
    {
        return SOAStart::SUB_INDENT . ')';
    }

    static public function IsMy( $c, $file )
    {
        return ltrim( $c )[0] === ')';
    }
}

class DNSEntry extends ParsedItem
{
    const TYPE_REGEX = '(NS|A|AAAA|TXT|CNAME|MX)';

    /** @var string */
    public $Host;
    /** @var bool If line starts with Type, this is true; then Host contains value from entry above */
    public $IsHostOmitted;
    /** @var string NS|A|... */
    public $Type;
    /** @var int|null Priority, can be set for MX types */
    public $Priority;
    /** @var string Value for this host */
    public $Value;

    /**
     * @param string     $content
     * @param ConfigFile $file
     */
    public function __construct( $content, $file )
    {
        $parts = preg_split( '/\s+/', $content, 4, PREG_SPLIT_NO_EMPTY );
        $idx   = 0;
        if( preg_match( '/' . self::TYPE_REGEX . '/', $parts[0] ) )
        {
            $this->IsHostOmitted = true;
            for( $i = count( $file->Lines ) - 1; $i >= 0 && !$this->Host; --$i )
            {
                $item = $file->Lines[$i]->Item;
                if( $item instanceof DNSEntry )
                    $this->Host = $item->Host;
                else if( $item instanceof SOAStart )
                    $this->Host = $item->Domain;
            }
            if( !$this->Host )
                throw new ParseZoneException( "Could not detect omitted host for: $content" );
        }
        else
        {
            $idx                 = 1;
            $this->IsHostOmitted = false;
            $this->Host          = $parts[0];
            if( !preg_match( '/^' . self::TYPE_REGEX . '$/', $parts[1] ) )
                throw new ParseZoneException( "Cant parse dns entry: $content" );
        }
        $this->Type = $parts[$idx++];
        if( $this->Type === 'MX' && is_numeric( $parts[$idx] ) )
            $this->Priority = $parts[$idx++];
        $this->Value = isset( $parts[$idx + 1] ) ? $parts[$idx] . ' ' . $parts[$idx + 1] : $parts[$idx];
    }

    function __toString()
    {
        $value = $this->Type === 'MX' && $this->Priority != null ? $this->Priority . ' ' . $this->Value : $this->Value;
        return sprintf( '%-13s %-5s %s', $this->IsHostOmitted ? '' : $this->Host, $this->Type, $value );
    }

    static public function IsMy( $c, $file )
    {
        return preg_match( '/\s' . self::TYPE_REGEX . '\s/', $c );
    }
}

class FileParser
{
    /** @var ConfigFile Currently parsed file */
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
        static $classes = [ 'DNSEntry', 'EmptyLine', 'TTL', 'Origin', 'SOAStart', 'SOASerial', 'SOARefresh', 'SOARetry', 'SOAExpiry', 'SOACaching', 'SOAEnd' ];
        $dest_class = 'UnknownContent';
        foreach( $classes as $c_name )
            if( call_user_func( "\\" . __NAMESPACE__ . "\\$c_name::IsMy", $c, $this->_file ) )
            {
                $dest_class = $c_name;
                break;
            }
        $dest_class = "\\" . __NAMESPACE__ . "\\$dest_class";
        return new $dest_class( $c, $this->_file );
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
            if( $line->Item instanceof UnknownContent )
                $info = 'UnknownContent';
            else
                $info = self::_Debugtem( $line->Item );
            $s .= ';;;;;;; ' . $info . "\n" . $line->__toString() . "\n\n";
        }
        return $s;
    }

    /**
     * @param ParsedItem $item
     * @return string
     */
    static private function _Debugtem( $item )
    {
        $class = get_class( $item ); // class with namespace
        $o     = new \ReflectionObject( $item );
        return substr( $class, strrpos( $class, '\\' ) + 1 ) . '  ' . join( '  ', array_map( function ( $prop ) use ( $item )
        {
            $val = $item->{$prop->name};
            $val === true && $val = 'true';
            $val === false && $val = 'false';
            $val === null && $val = 'null';
            return "[$prop->name]=$val";
        }, $o->getProperties() ) );
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