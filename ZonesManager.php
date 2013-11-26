<?

namespace ZonesManager;

class ParseZoneException extends \Exception
{

}

/**
 * A line of parsed config. Consists of comment and actual content.
 */
class ConfigLine
{
    /** @var null|string Comment at end of line */
    public $Comment = null;
    /** @var bool|int If set, it is x-position of comment start; helps to remain comments alignment if actual content width is changed (content is edited) */
    public $CommentStart = false;
    /** @var ActualContent Actual content */
    public $Item;

    /**
     * @param ActualContent $item
     * @param string|null   $comment
     * @param int|bool      $commentStart
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

/**
 * A parsed config file, is an array of parsed config lines.
 * Lines can be added, removed, filtered
 */
class ConfigFile
{
    /** @var ConfigLine[] All parsed lines in the file */
    protected $_lines = [ ];

    /**
     * Append a line to end of file.
     * @param ConfigLine $line
     */
    public function AddLine( $line )
    {
        $this->_lines[] = $line;
    }

    /**
     * @param int|ConfigLine $line Pointer to line or its index in Lines array
     */
    public function RemoveLine( $line )
    {
        $index = is_numeric( $line ) ? $line : array_search( $line, $this->_lines );
        if( $index !== false && $index !== null && array_key_exists( $index, $this->_lines ) )
            array_splice( $this->_lines, $index, 1 );
    }

    /**
     * Get last actual item (not comment or empty line)
     * @return ActualContent|null
     */
    public function LastItem()
    {
        for( $i = count( $this->_lines ) - 1; $i >= 0; --$i )
            if( !( $this->_lines[$i]->Item instanceof EmptyLine ) ) // if a line has only a comment, it's also stored as EmptyLine
                return $this->_lines[$i]->Item;
        return null;
    }

    /**
     * Checks that last (for now) actual item (not comment or empty line) is of given class.
     * @param string $parsedItemClassName
     * @return bool
     */
    public function IsLastItemOfType( $parsedItemClassName )
    {
        $last = $this->LastItem();
        return $last && get_class( $last ) === __NAMESPACE__ . '\\' . $parsedItemClassName;
    }

    /**
     * Loop through items (actual content of lines, no comments) with possible type filtering
     * @param Callback    $callback  Function to be called for each looped item (accepts $item)
     * @param string|null $className Possible class name (without namespace, local) to filter line items
     * @param bool        $reverse   If true, cycle through last line to first (otherwise - default - from first to last)
     */
    public function EnumItems( $callback, $className = null, $reverse = false )
    {
        if( $className !== null && $className[0] !== '\\' )
            $className = '\\' . __NAMESPACE__ . '\\' . $className;
        for( $i = $reverse ? count( $this->_lines ) - 1 : 0; $i < count( $this->_lines ) && $i >= 0; $i += $reverse ? -1 : 1 )
            if( !$className || $this->_lines[$i]->Item instanceof $className )
            {
                $res = $callback( $this->_lines[$i]->Item, $this->_lines[$i], $i );
                if( $res === false ) // callback can return false to interrupt loop
                    break;
            }
    }

    function __toString()
    {
        return join( "\n", array_map( function ( $v )
        {
            /** @var $v ConfigLine */
            return $v->__toString();
        }, $this->_lines ) );
    }
}

/**
 * Represents actual content (when comments are stripped out) of one parsed line.
 * All line-content classes must extend this.
 * Names of available contents to parse are listed in FileParser::_ParseActualContent.
 */
abstract class ActualContent
{
    abstract function __toString();

    /**
     * Checks if current line content is of this concrete type
     * @param string     $c Actual content of RAW config line (without comment)
     * @param ConfigFile $file
     * @return bool
     * @abstract
     */
    static public function IsMy( $c, $file )
    {
        /* abstract, has to be overridden (static methods can't be marked abstract in php) */
    }
}

class UnknownContent extends ActualContent
{
    public $Content; // if content of line can't be recognized, then it is stored 'as is' in this class

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
        return true; // this is executed when all others returned false
    }
}

class EmptyLine extends ActualContent
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

class Origin extends ActualContent
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

class TTL extends ActualContent
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

class SOAStart extends ActualContent
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

class SOASerial extends ActualContent // SOA serial and other SOA data inside brackets should be on separate lines (this is a common format)
{
    public $Number; // format: YYYYMMDDRR

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

class SOARefresh extends ActualContent
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

class SOARetry extends ActualContent
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

class SOAExpiry extends ActualContent
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

class SOACaching extends ActualContent
{
    public $Value;
    /** @var bool If closing bracket is in this line (this is last proprty inside SOA brackets); if false, SOAEnd line exists */
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

class SOAEnd extends ActualContent
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

class DNSEntry extends ActualContent
{
    const TYPE_REGEX = '(NS|A|AAAA|TXT|CNAME|MX)';

    /** @var string */
    public $Host;
    /** @var bool If line starts with Type (not with Host), this is true; then Host contains value from entry above */
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
            if( !$file )
                throw new ParseZoneException( "Could not detect omitted host, because file is null" );
            $file->EnumItems( function ( $item )
            {
                if( $item instanceof DNSEntry )
                    $this->Host = $item->Host;
                else if( $item instanceof SOAStart )
                    $this->Host = $item->Domain;
                return !$this->Host; // stop looping when first found
            }, null, true ); // loop in reverse order
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
        $this->Value = str_replace( '\\;', ';', $this->Value );
    }

    function __toString()
    {
        $value = str_replace( ';', '\\;', $this->Value );
        switch( $this->Type )
        {
        case 'MX':
            if( $this->Priority != null )
                $value = $this->Priority . ' ' . $value;
            break;
        case 'TXT':
            $value = '"' . trim( $value, '"' ) . '"'; // "asdf" => no change, asdf => "asdf", "a" " b" => no change
            break;
        }
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
        foreach( $lines as $line ) // convert each raw line to parsed line
            $this->_file->AddLine( $this->_ParseLine( rtrim( $line ) ) );
        return $this->_file;
    }

    /**
     * Parse single line of raw config - separate comment from actual content (and parse it).
     * @param string $line Line of raw config
     * @return ConfigLine
     */
    private function _ParseLine( $line )
    {
        // comment starts from ; but not from \; (backslashing ';' can be used in TXT entries)
        $c_start = false;
        for( $pos = 0; $c_start === false && $pos !== false && $pos < strlen( $line ); )
        {
            $pos = strpos( $line, ';', $pos );
            if( $pos !== false && $line[$pos - 1] !== '\\' )
                $c_start = $pos;
            else
                $pos++;
        }
        $comment        = $c_start !== false ? substr( $line, $c_start ) : null; // comment itself (contains ';' and all next)
        $actual_content = $c_start !== false ? rtrim( substr( $line, 0, $c_start ) ) : $line; // before comment
        return new ConfigLine( $this->_ParseActualContent( $actual_content ), $comment, $c_start );
    }

    /**
     * Parse actual content - a line cleaned from comment
     * @param string $c Content to parse
     * @return ActualContent
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

/**
 * External interface to this namespace.
 * Has the functionality to parse file/strings, modify parsed content (SOA entry, TTL, add/replace dns records) and save it.
 */
class ZonesManager
{
    /** @var ConfigFile */
    private $_file;

    /**
     * Generate raw config in bind9 format.
     * It can be saved as a zone file and then parsed again.
     * @return string
     */
    function GenerateConfig()
    {
        return $this->_file->__toString();
    }

    /**
     * Debug view of parsed file
     * @return string
     */
    function DebugOutput()
    {
        $s = '';
        $this->_file->EnumItems( function ( $item, $line ) use ( &$s )
        {
            if( $item instanceof UnknownContent )
                $info = 'UnknownContent';
            else
                $info = self::_DebugItem( $item );
            $s .= ';;;;;;; ' . $info . "\n" . $line . "\n\n";
        } );
        return $s;
    }

    /**
     * Get value of an item, that exists only once in a config file (e.g., Origin).
     * @param string $className Class name (without namespace) extending ParsedItem that represents storage
     * @param string $fieldName
     * @return string|null
     */
    private function _GetSingleValue( $className, $fieldName = 'Value' )
    {
        $value = null;
        $this->_file->EnumItems( function ( $v ) use ( &$value, $fieldName ) // this callback is assumed to be called once
        {
            $value = $v->$fieldName;
        }, $className );
        return $value;
    }

    /**
     * Set value of an item, that exists only once in a config file (e.g., TTL).
     * If an item doesn't exist in a file, it is not created.
     * @param string $className Class name (without namespace) extending ParsedItem that represents storage
     * @param string $newValue
     * @param string $fieldName
     */
    private function _SetSingleValue( $className, $newValue, $fieldName = 'Value' )
    {
        $this->_file->EnumItems( function ( $v ) use ( &$newValue, $fieldName ) // this callback is assumed to be called once
        {
            isset( $newValue ) and $v->$fieldName = $newValue;
        }, $className );
    }

    /**
     * Get $ORIGIN entry value (the start of this zone file in the namespace)
     */
    public function GetOrigin()
    {
        return $this->_GetSingleValue( 'Origin' );
    }

    /**
     * Set $ORIGIN entry value
     */
    public function SetOrigin( $value )
    {
        $this->_SetSingleValue( 'Origin', $value );
    }

    /**
     * Get $TTL entry value (default expiration time of all resource records without their own TTL value)
     */
    public function GetTTL()
    {
        return $this->_GetSingleValue( 'TTL' );
    }

    /**
     * Set $TTL entry value (format is arbitrary: "1h", "86400" and so on)
     */
    public function SetTTL( $value )
    {
        $this->_SetSingleValue( 'TTL', $value );
    }

    /**
     * Get all information about SOA entry.
     * @return array Array with keys like serial, ns, etc.
     */
    public function GetSOAInfo()
    {
        $soa = [ ];
        $this->_file->EnumItems( function ( $item ) use ( &$soa )
        {
            if( $item instanceof SOAStart )
            {
                $soa['domain'] = $item->Domain;
                $soa['ns']     = $item->Ns;
                $soa['email']  = $item->EMail;
            }
            else if( $item instanceof SOASerial )
                $soa['serial'] = $item->Number;
            else if( $item instanceof SOARefresh )
                $soa['refresh'] = $item->Value;
            else if( $item instanceof SOARetry )
                $soa['retry'] = $item->Value;
            else if( $item instanceof SOAExpiry )
                $soa['expiry'] = $item->Value;
            else if( $item instanceof SOACaching )
                $soa['caching'] = $item->Value;
        } );
        return $soa;
    }

    /**
     * Update information about SOA entry.
     * @param string[] $soa Array with the same keys as in GetSOAInfo; some keys can be omitted - only existing keys are applied
     */
    public function SetSOAInfo( $soa )
    {
        $this->_file->EnumItems( function ( $item ) use ( &$soa )
        {
            if( $item instanceof SOAStart )
            {
                isset( $soa['domain'] ) and $item->Domain = $soa['domain'];
                isset( $soa['email'] ) and $item->EMail = str_replace( '@', '.', $soa['email'] );
                isset( $soa['ns'] ) and $item->Ns = $soa['ns'];
            }
            else if( $item instanceof SOASerial && isset( $soa['serial'] ) )
                $item->Number = $soa['serial'];
            else if( $item instanceof SOARefresh && isset( $soa['refresh'] ) )
                $item->Value = $soa['refresh'];
            else if( $item instanceof SOARetry && isset( $soa['retry'] ) )
                $item->Value = $soa['retry'];
            else if( $item instanceof SOAExpiry && isset( $soa['expiry'] ) )
                $item->Value = $soa['expiry'];
            else if( $item instanceof SOACaching && isset( $soa['caching'] ) )
                $item->Value = $soa['caching'];
        } );
    }

    /**
     * Update SOA serial number. Must be called before saving a real file every time anything changed (to invalidate dns cache).
     * Format of serial number is: YYYYMMDDRR, where:
     *      YYYY - current year
     *      MM   - current month
     *      DD   - current day
     *      RR   - revision in current day (starting from 00)
     */
    public function UpdateSOASerial()
    {
        $this->_file->EnumItems( function ( $serial )
        {
            /** @var SOASerial $serial */
            $cur_date_start = date( 'Ymd' );
            $revision       = substr( $serial->Number, 0, 8 ) === $cur_date_start ? (int)substr( $serial->Number, 8 ) + 1 : 0; // inc revision if current date
            $serial->Number = $cur_date_start . sprintf( '%02d', $revision );
        }, 'SOASerial' );
    }

    /**
     * Get all dns entries (everything but SOA)
     * @return array
     * @see FilterDNS
     */
    public function GetAllDNS()
    {
        return $this->FilterDNS();
    }

    /**
     * Filter all dns entries by specified parameters. All of them are optional (if null, no filter is applied to such property)
     * @param string|null $host
     * @param string|null $type
     * @param int|null    $priority
     * @return array Array of keyvalue array [ host=>...  type=>(NS|A|...)  priority=>null|int  value=>... ]
     */
    public function FilterDNS( $host = null, $type = null, $priority = null )
    {
        $zones = [ ];
        $this->_file->EnumItems( function ( $item ) use ( &$zones, $host, $type, $priority )
        {
            /** @var DNSEntry $item */
            if( ( !$host || $item->Host === $host ) && ( !$type || $item->Type === $type ) && ( !$priority || $item->Priority == $priority ) )
                $zones[] = [ 'host' => $item->Host, 'type' => $item->Type, 'priority' => $item->Priority, 'value' => $item->Value ];
        }, 'DNSEntry' );
        return $zones;
    }

    /**
     * Add new DNS entry.
     * @param string      $host
     * @param string      $type     NS|A|...
     * @param string      $value
     * @param int|null    $priority Can be specified for MX
     * @param string|null $comment  Comment for line if config will be saved
     */
    public function AddDNS( $host, $type, $value, $priority = null, $comment = null )
    {
        $value = trim( $value );
        $zone  = new DNSEntry( "$host $type $priority $value", null );
        $this->_file->AddLine( new ConfigLine( $zone, isset( $comment ) ? '; ' . $comment : null ) );
    }

    /**
     * Remove DNS entry
     * @param string      $host
     * @param string      $type     NS|A|...
     * @param string|null $value    Needed to detect a single entry when there are multiple entries with equal host and type (e.g, '@ NS ns1', '@ NS ns2')
     * @param int|null    $priority Can be specified for MX (if not set and type is MX, all MX entries will be removed)
     */
    public function RemoveDNS( $host, $type, $value = null, $priority = null )
    {
        $this->_file->EnumItems( function ( $item, $line, &$forIndex ) use ( $host, $value, $type, $priority )
        {
            /** @var DNSEntry $item */
            if( $item->Host === $host && $item->Type === $type && ( !$value || $item->Value == $value ) && ( !$priority || $item->Priority == $priority ) )
            {
                $this->_file->RemoveLine( $line );
                $forIndex--; // hack
            }
        }, 'DNSEntry' );
    }

    /**
     * Replace DNS entry with new properties.
     * All new parameters are optional: if special not set, it won't be changed.
     */
    public function ReplaceDNS( $oldHost, $oldType, $oldValue = null, $oldPriority = null, $newHost = null, $newType = null, $newValue = null, $newPriority = null )
    {
        $this->_file->EnumItems( function ( $item ) use ( $oldHost, $oldType, $oldValue, $oldPriority, $newHost, $newType, $newValue, $newPriority )
        {
            /** @var DNSEntry $item */
            if( $item->Host === $oldHost && $item->Type === $oldType && ( !$oldValue || $item->Value === $oldValue ) && ( !$oldPriority || $item->Priority == $oldPriority ) )
            {
                isset( $newHost ) and $item->Host = $newHost;
                isset( $newType ) and $item->Type = $newType;
                isset( $newValue ) and $item->Value = $newValue;
                isset( $newPriority ) and $item->Priority = $newPriority;
            }
        }, 'DNSEntry' );
    }

    /**
     * Set new value for DNS entry with specified properties.
     * @param string   $host
     * @param string   $type     NS|A|...
     * @param string   $newValue A string containing new value
     * @param int|null $priority Can be specified for MX
     */
    public function SetDNSValue( $host, $type, $newValue, $priority = null )
    {
        $this->ReplaceDNS( $host, $type, $priority, null, null, $newValue );
    }

    /**
     * Save generated config into a file. If a file exists already, it is replaced.
     * @param string $fullFileName    Full path to saving file; be sure that its directory exists before calling this.
     * @param bool   $updateSOASerial Update serial number of SOA entry (increment current day revision or start a new day)
     */
    public function SaveFile( $fullFileName, $updateSOASerial = true )
    {
        $updateSOASerial && $this->UpdateSOASerial();
        file_put_contents( $fullFileName, $this->GenerateConfig() );
    }

    /**
     * @param ActualContent $item
     * @return string
     */
    static private function _DebugItem( $item )
    {
        $class = get_class( $item ); // class with namespace
        $o     = new \ReflectionObject( $item );
        return substr( $class, strrpos( $class, '\\' ) + 1 ) . '  ' . join( '  ', array_map( function ( $prop ) use ( $item )
        {
            $val = $item->{$prop->name};
            $val === true && $val = 'true'; // represent some cases in readable format
            $val === false && $val = 'false';
            $val === null && $val = 'null';
            return "[$prop->name]=$val";
        }, $o->getProperties() ) );
    }

    /**
     * Create instance from file contents
     * @param string $fullFileName Full path to a readable file
     * @return ZonesManager
     */
    static public function FromFile( $fullFileName )
    {
        return self::FromString( file_get_contents( $fullFileName ) );
    }

    /**
     * Create instance from string containing lines of raw zone config.
     * @param string $str Raw config (lines must be separated with \n)
     * @return ZonesManager
     */
    static public function FromString( $str )
    {
        $zm        = new ZonesManager();
        $zm->_file = ( new FileParser() )->ParseLines( explode( "\n", $str ) );
        return $zm;
    }
}