<?php
/**
* Full featured Http Client class in pure PHP (4.1+)
*
* API list:
* Object  $http = new Http_Client([bool $verbose = false]);
* integer $http->getStatus();
* string  $http->getTitle();
* string  $http->getUrl();
* void    $http->setHeader(string $key[, string $value = null]);
* mixed   $http->getHeader([string $key = null]);
* void    $http->setCookie(string $key, string $value);
* mixed   $http->getCookie([string $key = null[, string $host = null]]);
* bool    $http->saveCookie(string $filepath);
* bool    $http->loadCookie(string $filepath);
* void    $http->addPostField(string $key, mixed $value);
* void    $http->addPostFile(string $key, string $filename[, string $content = null]);
* string  $http->Get(string $url[, bool $redirect = true]);
* mixed   $http->Head(string $url[, bool $redirect = true]);
* string  $http->Post(string $url[, bool redirect = true]);
* bool    $http->Download(string $url[, string $filepath = null[, bool overwrite = false]);
*
* @author hightman <hightman@twomice.net>
* @link http://www.hightman.cn/
* @copyright Copyright &copy; 2008-2010 Twomice Studio
* @version $Id: http_client.class.php,v 1.22 2010/10/16 16:42:47 hightman Exp $
*/

/**
* Defines the package name.
*/
define ('HC_PACKAGENAME',    'HttpClient');
/**
* Defines the package version.
*/
define ('HC_VERSION',        '2.0-beta');
/**
* This constant defines how many times should be tried on I/O failure (timeout and error).
* Defaults to 3, it should be greater than 0.
*/
define ('HC_MAX_RETRIES',    3);

/**
* Http_Client is a full featured client class for the HTTP protocol.
*
* It currently implements some HTTP/1.x protocols, including request method HEAD, GET, POST,
* and automatic handling of authorization, redirection request, and cookies.
*
* Features include:
* 1) Pure PHP code, none of extensions is required, PHP version just from 4.1.0;
* 2) Ability to set/get any HTTP request headers, such as user-agent, referal page, etc;
* 3) Includes full featured cookie support, automatic sent cookie header if required on next request;
* 4) Handle redirected requests automatically (such as HTTP/301, HTTP/302);
* 5) Support real Keep-Alive connections, used for multiple requests;
* 6) Can resume getting a partially-downloaded file use special download() method;
* 7) Support multiple files upload via post method, support array named request variable (arr[]=...)
* 8) SSL support
*
* The whole library code is open and free, you can use it for any purposes.
*
* @author hightman <hightman@twomice.net>
* @version 2.0-beta $
*/
class Http_Client
{
    /**
     * local private variables
     * @access private
     */
    var $headers, $status, $title, $cookies, $socks, $url, $filepath, $verbose;
    var $post_files, $post_fields;
    
    /** 
     * Constructor (PHP4-style).
     * @param boolean wheather to display verbose execute messages
     */
    function Http_Client($verbose = false)
    {
        $this->__construct($verbose);
    }
    
    /** 
     * Constructor (PHP5).
     * @param boolean wheather to display verbose execute messages
     */
    function __construct($verbose = false)
    {
        $this->verbose = $verbose;
        $this->cookies = array();
        $this->socks = array();    
        $this->_reset();
    }

    /** 
     * Destructor (PHP5 only).
     * Close all opened socket connections.
     */
    function __destruct()
    {
        foreach ($this->socks as $host => $sock) { @fclose($sock); }
        $this->socks = array();
    }

    /** 
     * Get HTTP respond status code of the last HTTP request.
     * @return integer http respond status code
     */
    function getStatus()
    {
        return $this->status;
    }

    /** 
     * Get HTTP respond short title of the last HTTP request.
     * @return string http respond short title
     */
    function getTitle()
    {
        return $this->title;
    }
    
    /** 
     * Get the real URL of the last HTTP request.
     * @return string real URL of the last http request after redirecting
     */    
    function getUrl()
    {
        return $this->url;
    }

    /** 
     * Get the downloaded file path after calling Download() method.
     * @return string filepath saved on local disk
     */    
    function getFilepath()
    {
        return $this->filepath;
    }

    /** 
     * Set a HTTP header for the next request.
     * @param string the name of the request header
     * @param string the value of the request header
     * If the value is NULL, the header will be dropped.
     * Note: special key 'x-server-addr' will force to use instead of gethostbyname(host)
     */
    function setHeader($key, $value = null)
    {
        $this->_reset();
        $key = strtolower($key);
        if (is_null($value)) unset($this->headers[$key]);
        else $this->headers[$key] = strval($value);
    }
    
    /** 
     * Get one or more HTTP headers of the last request.
     * @param string the name of the header to be fetched.
     * If is NULL, return the all headers of the last request.
     * @return mixed fetched header value or headersas key-value array.
     * If the header dose not exists, NULL is returned.
     */    
    function getHeader($key = null)
    {
        if (is_null($key)) return $this->headers;
        $key = strtolower($key);
        if (!isset($this->headers[$key])) return null;
        return $this->headers[$key];
    }

    /** 
     * Add a HTTP cookie sent for the next request.
     * @param string the name of the cookie to be added
     * @param string the value of the cookie to be added
     */
    function setCookie($key, $value)
    {
        $this->_reset();
        if (!isset($this->headers['cookie'])) $this->headers['cookie'] = array();
        $this->headers['cookie'][$key] = $value;
    }

    /** 
     * Get a HTTP cookie item by name
     * @param string the name of the cookie to be fetched
     * If the name is NULL, all matched cookies are returned as key-value array.  
     * @param string host of all saved cookies (include expired)
     * If the host is NULL, fetch the cookie from last request.
     * @return mixed fetched cookie item or cookies as key-value array.
     * Every cookie item is a assoc array, keys include: value, expires, path, host
     * If the cookie dose not exists, NULL is returned.    
     */
    function getCookie($key = null, $host = null)
    {
        // fetch from last request
        if (!is_null($key)) $key = strtolower($key);
        if (is_null($host))
        {
            if (!isset($this->headers['cookie'])) return null;
            if (is_null($key)) return $this->headers['cookie'];
            if (!isset($this->headers['cookie'][$key])) return null;
            return $this->headers['cookie'][$key];
        }
        // fetch from all saved cookies.
        $host = strtolower($host);
        while (true)
        {
            if (isset($this->cookies[$host]))
            {
                if (is_null($key)) return $this->cookies[$host];
                if (isset($this->cookies[$host][$key])) return $this->cookies[$host][$key];
            }
            // search for next sub-domain
            $pos = strpos($host, '.', 1);
            if ($pos === false) break;
            $host = substr($host, $pos);
        }
        return null;
    }

    /** 
     * Save all cookies to a file.
     * @param string the file path that cookies will be saved to.
     * @return boolean save result, return true on success and false on faiulre.
     * Note: all cookies are serialized before saving.
     */
    function saveCookie($fpath)
    {
        if (false === ($fd = @fopen($fpath, 'w')))
            return false;
        $data = serialize($this->cookies);
        fwrite($fd, $data);
        fclose($fd);
        return true;
    }

    /** 
     * Load cookies from a file
     * @param string the file path that cookies has been saved to.
     * The cookie file should be created by saveCookie() method.
     */
    function loadCookie($fpath)
    {
        if (file_exists($fpath) && ($cookies = @unserialzie(file_get_contents($fpath))))
            $this->cookies = $cookies;
    }

    /** 
     * Add a post field for the next request
     * @param string the name of the field.
     * @param mixed the value of the field, can be array or string.
     * If the value is an array, converted to arr[key][key2] fields automatically.
     */
    function addPostField($key, $value)
    {
        $this->_reset();
        if (!is_array($value))
            $this->post_fields[$key] = strval($value);
        else
        {
            $value = $this->_format_array_field($value);
            foreach ($value as $tmpk => $tmpv)
            {
                $tmpk = $key . '[' . $tmpk . ']';
                $this->post_fields[$tmpk] = strval($tmpv);
            }
        }
    }

    /**
     * Add a multipart post file for the next request
     * @param string the name of the field
     * @param string the filename or filepath to be uploaded
     * @param string content the file content
     * If the content is null and fname is a valid filepath, 
     * content will be set to the file content.
     */
    function addPostFile($key, $fname, $content = '')
    {
        $this->_reset();
        if ($content === '' && is_file($fname)) $content = @file_get_contents($fname);
        $this->post_files[$key] = array(basename($fname), $content);
    }

    /**
     * Do a http request via get method
     * @param string the absolute URL
     * @param boolean handle redirected requests automatically or not
     * @return string respond body data or false on failure before server respond.
     */
    function Get($url, $redir = true)
    {
        return $this->_do_url($url, 'get', null, $redir);
    }

    /**
     * Do a http request via head method
     * @param string the absolute URL
     * @param boolean handle redirected requests automatically or not     
     * @return mixed all respond HTTP header or false on failure before server respond.
     */
    function Head($url, $redir = false)
    {
        if ($this->_do_url($url, 'head', null, $redir) !== false)
            return $this->getHeader(null);
        return false;
    }

    /**
     * Do a http request via post method
     * @param string the absolute URL
     * @param boolean handle redirected requests automatically or not
     * @return string respond body data or false on failure before server respond.
     * Note: post request variable should be set by ::addPostField() and ::addPostFile()
     */
    function Post($url, $redir = true)
    {
        $data = '';
        if (count($this->post_files) > 0)
        {
            $boundary = md5($url . microtime());
            foreach ($this->post_fields as $tmpk => $tmpv)
            {
                $data .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$tmpk}\"\r\n\r\n{$tmpv}\r\n";
            }
            foreach ($this->post_files as $tmpk => $tmpv)
            {
                $type = 'application/octet-stream';
                $ext = strtolower(substr($tmpv[0], strrpos($tmpv[0],'.')+1));
                if (isset($GLOBALS['___HC_MIMES___'][$ext])) $type = $GLOBALS['___HC_MIMES___'][$ext];
                $data .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$tmpk}\"; filename=\"{$tmpv[0]}\"\r\nContent-Type: $type\r\nContent-Transfer-Encoding: binary\r\n\r\n";
                $data .= $tmpv[1] . "\r\n";
            }
            $data .= "--{$boundary}--\r\n";
            $this->setHeader('content-type', 'multipart/form-data; boundary=' . $boundary);
        }
        else
        {
            foreach ($this->post_fields as $tmpk => $tmpv)
            {
                $data .= '&' . rawurlencode($tmpk) . '=' . rawurlencode($tmpv);
            }
            $data = substr($data, 1);
            $this->setHeader('content-type', 'application/x-www-form-urlencoded');
        }
        $this->setHeader('content-length', strlen($data));
        return $this->_do_url($url, 'post', $data, $redir);
    }

    /**
     * Download a file to local via get method with range support
     * @param string the absolute URL
     * @param string local filepath to saved, default is the same filename on current working directory.
     * @param boolean weather to overwrite the exists file 
     * when filepath exists and not a valid partially-downloaded file.
     * @return boolean true on success and false on failure.
     * Note: this method can be used to resume getting a partially-downloaded file.
     */
    function Download($url, $filepath = null, $overwrite = false)
    {
        // get filepath & head
        if ($filepath === true)
        {
            $overwrite = true; 
            $filepath = null;
        }
        if (is_null($filepath) || empty($filepath)) $filepath = '.';
        // get normal headers first
        $savehead = $this->getHeader(null);
        if (!$this->Head($url, true))
        {
            if ($this->verbose) echo "[ERROR] failed to get headers for downloading file.\n";
            return false;
        }
        else if ($this->getStatus() != 200)
        {
            if ($this->verbose) echo "[ERROR] can not get a valid 200 HTTP respond status.\n";
            return false;
        }
        // get filename & filesize
        $url = $this->getUrl();
        if ($this->verbose) echo "[INFO] real download url is: $url\n";
        if (is_dir($filepath))
        {
            if (substr($filepath, -1, 1) != DIRECTORY_SEPARATOR) $filepath .= DIRECTORY_SEPARATOR;        
            if (($disposition = $this->getHeader('content-disposition')) 
                && preg_match('/filename=[\'"]?([^;\'" ]+)/', $disposition, $match))
            {
                $filename = $match[1];
                if ($this->verbose) echo "[INFO] fetch filename from disposition header: $filename\n";
            }
            else
            {
                $tmpstr = ($pos = strpos($url, '?')) ? substr($url, 0, $pos) : $url;
                $pos = strrpos($tmpstr, '/');
                $filename = substr($tmpstr, $pos + 1);
                if ($filename == '') $filename = 'index.html';
                if ($this->verbose) echo "[INFO] fetch filename from URL: $filename\n";
            }
            while (true)
            {
                $filepath .= $filename;
                if (!is_dir($filepath)) break;
                $filepath .= DIRECTORY_SEPARATOR . $filename;
            }
        }
        // check filepath
        if (!file_exists($filepath) || !($fsize = @filesize($filepath)))
        {
            $savefd = @fopen($filepath, 'w');
            if ($this->verbose) echo "[INFO] save file directly to: $filepath\n";
        }
        else
        {
            $length = $this->getHeader('content-length');
            $accept = $this->getHeader('accept-ranges');
            if ($length && $fsize < $length && stristr($accept, 'bytes'))
            {
                // range request used
                $this->setHeader('range', 'bytes=' . $fsize . '-');
                $savefd = @fopen($filepath, 'a');
                if ($this->verbose) echo "[INFO] range download used, range: {$fsize}-\n";
            }
            else if ($overwrite)
            {
                $savefd = @fopen($filepath, 'w');
                if ($this->verbose) echo "[INFO] overwrite the exists file: $filepath\n";
            }
            else
            {
                // auto append filename '.1, .2, ...'
                for ($i = 1; @file_exists($filepath . '.' . $i); $i++);
                $filepath .= '.' . $i;
                $savefd = @fopen($filepath, 'w');
                if ($this->verbose) echo "[INFO] auto skip exists file, last save to: $filepath\n";
            }
        }
        // check the savefd
        if (!$savefd)
        {
            if ($this->verbose) echo "[ERROR] can not open the file to save data: $filename\n";
            return false;
        }
        // do real download via get method
        foreach ($savehead as $hk => $hv) $this->setHeader($hk, $hv);
        if ($this->_do_url($url, 'get', null, false, $savefd) !== false)
        {
            $this->filepath = $filepath;
            fclose($savefd);
            if ($this->verbose) echo "[INFO] downloaded file saved in: $filepath\n";
            return true;
        }
        else
        {
            if ($this->verbose) echo "[ERROR] can not download the URL: $url\n";
            return false;
        }
    }
    
    // -------------------------------------------------
    // private functions
    // -------------------------------------------------
    // read data from socket
    function _sock_read($fd, $maxlen = 4096, $wfd = false)
    {
        $rlen = 0;
        $data = '';
        $ntry = HC_MAX_RETRIES;
        while (!feof($fd))
        {
            $part = fread($fd, $maxlen - $rlen);
            if ($part === false || $part === '') $ntry--;
            else $data .= $part;
            $rlen = strlen($data);
            if ($rlen == $maxlen || $ntry == 0) break;
        }
        if ($ntry == 0 || feof($fd)) @fclose($fd);
        if (is_resource($wfd))
        {
            fwrite($wfd, $data);
            $data = '';
        }
        return $data;
    }

    // write data to socket
    function _sock_write($fd, $buf)
    {
        $wlen = 0;
        $tlen = strlen($buf);
        $ntry = HC_MAX_RETRIES;
        while ($wlen < $tlen)
        {
            $nlen = fwrite($fd, substr($buf, $wlen), $tlen - $wlen);
            if (!$nlen) { if (--$ntry == 0) return false; }
            else $wlen += $nlen;
        }
        return true;
    }

    // reset some request data (status)
    function _reset()
    {
        if ($this->status !== 0) 
        {
            $this->status = 0;
            $this->url = $this->title = $this->filepath = null;
            $this->headers = $this->post_files = $this->post_fields = array();
        }
    }
    
    // check is a host belong a domain
    function _belong_domain($host, $domain)
    {
        if (!strcasecmp($domain, $host)) return true;
        if (substr($domain, 0, 1) == '.')
        {
            if (!strcasecmp($host, substr($domain, 1))) return true;
            $hlen = strlen($host);
            $dlen = strlen($domain);
            if ($hlen > $dlen && !strcasecmp(substr($host, $hlen - $dlen), $domain))
                return true;
        }
        return false;
    }

    // format array field (convert N-DIM(n>=2) array => 2-DIM array)
    function _format_array_field($value, $pk = NULL)
    {
        $ret = array();
        foreach ($value as $k => $v)
        {
            $k = (is_null($pk) ? $k : $pk . $k);
            if (is_array($v)) $ret += $this->_format_array_field($v, $k . '][');
            else $ret[$k] = $v;
        }
        return $ret;
    }

    // do a url method
    function _do_url($url, $method, $data = null, $redir = true, $savefd = false)
    {
        // check the url
        if (strncasecmp($url, 'http://', 7) && strncasecmp($url, 'https://', 8) && isset($_SERVER['HTTP_HOST']))
        {
            $base = 'http://' . $_SERVER['HTTP_HOST'];
            if (substr($url, 0, 1) != '/')
                $url = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/')+1) . $url;            
            $url = $base . $url;
        }

        // parse the url
        $url = str_replace('&amp;', '&', $url);
        $pa = @parse_url($url);
        if ($pa['scheme'] && $pa['scheme'] != 'http' && $pa['scheme'] != 'https')
        {
            trigger_error("Invalid scheme `{$pa['scheme']}`", E_USER_WARNING);
            return false;
        }
        if (!isset($pa['host']))
        {
            trigger_error("Invalid request url, host required", E_USER_WARNING);
            return false;
        }
        if (!isset($pa['port'])) $pa['port'] = ($pa['scheme'] == 'https' ? 443 : 80);
        if (!isset($pa['path']))
        {
            $pa['path'] = '/';
            $url .= '/';
        }
        $host = strtolower($pa['host']);
        if (isset($this->headers['x-server-addr'])) $addr = $this->headers['x-server-addr'];
        else $addr = gethostbyname($pa['host']);
        $port = intval($pa['port']);
        $skey = $addr . ':' . $port;
        if ($pa['scheme'] && $pa['scheme'] == 'https') $host_conn = 'ssl://' . $addr;
        else $host_conn = 'tcp://' . $addr;

        // make the query buffer
        $method = strtoupper($method);
        $buf = $method . ' ' . $pa['path'];
        if (isset($pa['query'])) $buf .= '?' . $pa['query'];
        $buf .= " HTTP/1.1\r\nHost: {$host}\r\n";
        
        // basic auth support
        if (isset($pa['user']) && isset($pa['pass']))
            $this->headers['authorization'] = 'Basic ' . base64_encode($pa['user'] . ':' . $pa['pass']);

        // set default HTTP/headers
        $savehead = $this->headers;
        $this->_reset();
        if (!isset($this->headers['user-agent'])) 
        {
            $buf .= "User-Agent: Mozilla/5.0 (Compatible; " . HC_PACKAGENAME . "/" . HC_VERSION . "; +Hightman) ";
            $buf .= "php-" . php_sapi_name() . "/" . phpversion() . " ";
            $buf .= php_uname("s") . "/" . php_uname("r") . "\r\n";
        }
        if (!isset($this->headers['accept'])) $buf .= "Accept: */*\r\n";
        if (!isset($this->headers['accept-language'])) $buf .= "Accept-Language: zh-cn,zh\r\n";
        if (!isset($this->headers['connection'])) $buf .= "Connection: Keep-Alive\r\n";
        if (isset($this->headers['accept-encoding'])) unset($this->headers['accept-encoding']);
        if (isset($this->headers['host'])) unset($this->headers['host']);

        // saved cookies (session data)
        $now = time();
        $ck_str = '';
        foreach ($this->cookies as $ck_host => $ck_list)
        {
            if (!$this->_belong_domain($host, $ck_host)) continue;
            foreach ($ck_list as $ck => $cv)
            {
                if (isset($this->headers['cookie'][$ck])) continue;
                if ($cv['expires'] > 0 && $cv['expires'] < $now) continue;
                if (strncmp($pa['path'], $cv['path'], strlen($cv['path']))) continue;
                $ck_str .= '; ' . $cv['rawdata'];
            }
        }
        foreach ($this->headers as $k => $v)
        {
            if ($k != 'cookie')
                $buf .= ucfirst($k) . ": " . $v . "\r\n";
            else
            {
                foreach ($v as $ck => $cv) $ck_str .= '; ' . rawurlencode($ck) . '=' . rawurlencode($cv);
            }
        }
        // TODO: check cookie length?
        if ($ck_str != '') $buf .= 'Cookie:' . substr($ck_str, 1) . "\r\n";
        $buf .= "\r\n";
        if ($method == 'POST') $buf .= $data . "\r\n";

        // force reset status for next query even if failed this time.
        $this->status = -1;
        $this->url = $url;

        // show the header buf
        if ($this->verbose)
        {
            echo "[INFO] request url: $url\r\n";
            echo "[SEND] request buffer\r\n----\r\n";
            echo $buf;
            echo "----\r\n";
        }

        // create the sock & send the header
        $ntry = HC_MAX_RETRIES;
        $sock = isset($this->socks[$skey]) ? $this->socks[$skey] : false;
        do
        {
            if (is_resource($sock) && $this->_sock_write($sock, $buf)) break;
            if ($sock) @fclose($sock);
            $sock = @fsockopen($host_conn, $port, $errno, $error, 3);
            if ($sock)
            {
                stream_set_blocking($sock, 1);
                stream_set_timeout($sock, 10);
            }            
        }
        while (--$ntry);
        if (!$sock)
        {
            if (isset($this->socks[$skey])) unset($this->socks[$skey]);
            trigger_error("Cann't connect to `$host:$port'", E_USER_WARNING);
            return false;
        }
        $this->socks[$skey] = $sock;
        if ($this->verbose)
        {
            echo "[SEND] using socket = {$sock}\r\n";
            echo "[RECV] http respond header\r\n----\r\n";
        }

        // read the respond header
        $with_range = isset($this->headers['range']);
        $this->headers = array();
        while ($line = fgets($sock, 2048))
        {
            if ($this->verbose) echo $line;
            $line = trim($line);
            if ($line === '') break;
            if (!strncasecmp('HTTP/', $line, 5))
            {
                $line = trim(substr($line, strpos($line, ' ')));
                list($this->status, $this->title) = explode(' ', $line, 2);
                $this->status = intval($this->status);
            }
            else if (!strncasecmp('Set-Cookie: ', $line, 12))
            {
                // ignore the cookie options: Httponly
                $ck_key = '';
                $ck_val = array('value' => '', 'expires' => 0, 'path' => '/', 'domain' => $host);
                $tmpa = explode(';', substr($line, 12));
                foreach ($tmpa as $tmp)
                {
                    $tmp = trim($tmp);
                    if (empty($tmp)) continue;
                    list($tmpk, $tmpv) = explode('=', $tmp, 2);
                    $tmpk2 = strtolower($tmpk);
                    if ($ck_key == '')
                    {
                        $ck_key = rawurldecode($tmpk);
                        $ck_val['value'] = rawurldecode($tmpv);
                        $ck_val['rawdata'] = $tmpk . '=' . $tmpv;
                    }
                    else if ($tmpk2 == 'expires')
                    {
                        $ck_val['expires'] = strtotime($tmpv);
                        if ($ck_val['expires'] < $now)
                        {
                            $ck_val['value'] = '';
                            break;
                        }
                    }
                    else if (isset($ck_val[$tmpk2]) && $tmpv != '')
                    {
                        $ck_val[$tmpk2] = $tmpv;
                        // drop invalid-domain cookies?
                        if ($tmpk2 == 'domain' && !$this->_belong_domain($host, $tmpv)) $ck_key = '';
                    }
                }

                // delete cookie?
                if ($ck_key == '') continue;
                if ($ck_val['value'] == '') unset($this->cookies[$ck_val['domain']][$ck_key]);
                else $this->cookies[$ck_val['domain']][$ck_key] = $ck_val;

                // headers.
                $this->headers['cookie'][$ck_key] = $ck_val;
            }
            else 
            {
                list($k, $v) = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                $this->headers[$k] = $v;
            }
        }
        if ($this->verbose) echo "----\r\n";
        
        // check savefd
        if ($savefd && $with_range)
        {
            if ($this->status == 200)
            {
                ftruncate($savefd, 0);
                fseek($savefd, 0, SEEK_SET);
            }
            else if ($this->status != 206) $savefd = false;
        }

        // get body
        $connection = $this->getHeader('connection');
        $encoding = $this->getHeader('transfer-encoding');
        $length = $this->getHeader('content-length');
        if ($method == 'HEAD') 
        {
            // nothing to do
            $body = '';
        }
        else if ($encoding && !strcasecmp($encoding, 'chunked'))
        {
            $body = '';
            while (is_resource($sock))
            {
                if (!($line = fgets($sock, 1024))) break;
                if ($this->verbose) echo "[RECV] Chunk Line: " . $line;
                if ($p1 = strpos($line, ';')) $line = substr($line, 0, $pos);
                $chunk_len = hexdec(trim($line));
                if ($chunk_len <= 0) break;    // end the chunk
                $body .= $this->_sock_read($sock, $chunk_len, $savefd);
                fread($sock, 2);            // eat the CRLF
            }

            // trailer header
            if ($this->verbose) echo "[RECV] chunk tailer\r\n----\r\n";
            while ($line = fgets($sock, 2048))
            {
                if ($this->verbose) echo $line;
                $line = trim($line);
                if ($line === '') break;
                list($k, $v) = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                $this->headers[$k] = $v;
            }        
            if ($this->verbose) echo "----\r\n";
        }
        else if ($length)
        {
            $body = '';
            $length = intval($length);
            while ($length > 0 && is_resource($sock))
            {
                $body .= $this->_sock_read($sock, ($length > 8192 ? 8192 : $length), $savefd);
                $length -= 8192;
            }
        }
        else
        {
            $body = '';
            while (is_resource($sock) && !feof($sock)) $body .= $this->_sock_read($sock, 8192, $savefd);
            $connection = 'close';
        }        

        // check close connection?
        if ($connection && !strcasecmp($connection, 'close'))
        {
            @fclose($sock);
            unset($this->socks[$skey]);
        }
            
        // check redirect
        if ($redir && $this->status != 200 && ($location = $this->getHeader('location')))
        {
            if (!is_int($redir)) $redir = HC_MAX_RETRIES;
            if (!preg_match('/^http[s]?:\/\//i', $location))
            {
                $url2 = $pa['scheme'] . '://' . $pa['host'];
                if (strpos($url, ':', 8)) $url2 .= ':' . $pa['port'];
                if (substr($location, 0, 1) == '/') $url2 .= $location;
                else $url2 .= substr($pa['path'], 0, strrpos($pa['path'], '/') + 1) . $location;
                $location = $url2;
            }
            if (!isset($savehead['referer'])) $savehead['referer'] = $url;
            foreach ($savehead as $hk => $hv) $this->setHeader($hk, $hv);
            return $this->_do_url($location, ($method == 'HEAD' ? 'head' : 'get'), null, $redir - 1);
        }

        // return the body buf
        return $body;
    }
}

// mimetypes used on http_client
$GLOBALS['___HC_MIMES___'] = array(
    'gif' => 'image/gif',
    'png' => 'image/png',
    'bmp' => 'image/bmp',
    'jpeg' => 'image/jpeg',
    'pjpg' => 'image/pjpg',
    'jpg' => 'image/jpeg',
    'tif' => 'image/tiff',
    'htm' => 'text/html',
    'css' => 'text/css',
    'html' => 'text/html',
    'txt' => 'text/plain',
    'gz' => 'application/x-gzip',
    'tgz' => 'application/x-gzip',
    'tar' => 'application/x-tar',
    'zip' => 'application/zip',
    'hqx' => 'application/mac-binhex40',
    'doc' => 'application/msword',
    'pdf' => 'application/pdf',
    'ps' => 'application/postcript',
    'rtf' => 'application/rtf',
    'dvi' => 'application/x-dvi',
    'latex' => 'application/x-latex',
    'swf' => 'application/x-shockwave-flash',
    'tex' => 'application/x-tex',
    'mid' => 'audio/midi',
    'au' => 'audio/basic',
    'mp3' => 'audio/mpeg',
    'ram' => 'audio/x-pn-realaudio',
    'ra' => 'audio/x-realaudio',
    'rm' => 'audio/x-pn-realaudio',
    'wav' => 'audio/x-wav',
    'wma' => 'audio/x-ms-media',
    'wmv' => 'video/x-ms-media',
    'mpg' => 'video/mpeg',
    'mpga' => 'video/mpeg',
    'wrl' => 'model/vrml',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo'
);
?>