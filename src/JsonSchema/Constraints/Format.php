<?php

/*
 * This file is part of the JsonSchema package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JsonSchema\Constraints;

/**
 * Validates against the "format" property
 *
 * @author Justin Rainbow <justin.rainbow@gmail.com>
 * @link   http://tools.ietf.org/html/draft-zyp-json-schema-03#section-5.23
 */
class Format extends Constraint
{
    /**
     * {@inheritDoc}
     */
    public function check($element, $schema = null, $path = null, $i = null)
    {
        if (!isset($schema->format)) {
            return;
        }

        switch ($schema->format) {
            case 'date':
                if (!$date = $this->validateDateTime($element, 'Y-m-d')) {
                    $this->addError($path, sprintf('Invalid date %s, expected format YYYY-MM-DD', json_encode($element)));
                }
                break;

            case 'time':
                if (!$this->validateDateTime($element, 'H:i:s')) {
                    $this->addError($path, sprintf('Invalid time %s, expected format hh:mm:ss', json_encode($element)));
                }
                break;

            case 'date-time':
                if (!$this->validateDateTime($element, 'Y-m-d\TH:i:s\Z') &&
                    !$this->validateDateTime($element, 'Y-m-d\TH:i:s.u\Z') &&
                    !$this->validateDateTime($element, 'Y-m-d\TH:i:sP') &&
                    !$this->validateDateTime($element, 'Y-m-d\TH:i:sO')
                ) {
                    $this->addError($path, sprintf('Invalid date-time %s, expected format YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DDThh:mm:ss+hh:mm', json_encode($element)));
                }
                break;

            case 'utc-millisec':
                if (!$this->validateDateTime($element, 'U')) {
                    $this->addError($path, sprintf('Invalid time %s, expected integer of milliseconds since Epoch', json_encode($element)));
                }
                break;

            case 'regex':
                if (!$this->validateRegex($element)) {
                    $this->addError($path, 'Invalid regex format ' . $element);
                }
                break;

            case 'color':
                if (!$this->validateColor($element)) {
                    $this->addError($path, "Invalid color");
                }
                break;

            case 'style':
                if (!$this->validateStyle($element)) {
                    $this->addError($path, "Invalid style");
                }
                break;

            case 'phone':
                if (!$this->validatePhone($element)) {
                    $this->addError($path, "Invalid phone number");
                }
                break;

            case 'uri':
                if (!$this->validateUri($element)) {
                    $this->addError($path, "Invalid URI format");
                }
                break;

            case 'url':
                if (null === filter_var($element, FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE)) {
                    $this->addError($path, "Invalid URL format");
                }
                break;

            case 'email':
                if (null === filter_var($element, FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE)) {
                    $this->addError($path, "Invalid email");
                }
                break;

            case 'ip-address':
            case 'ipv4':
                if (null === filter_var($element, FILTER_VALIDATE_IP, FILTER_NULL_ON_FAILURE | FILTER_FLAG_IPV4)) {
                    $this->addError($path, "Invalid IP address");
                }
                break;

            case 'ipv6':
                if (null === filter_var($element, FILTER_VALIDATE_IP, FILTER_NULL_ON_FAILURE | FILTER_FLAG_IPV6)) {
                    $this->addError($path, "Invalid IP address");
                }
                break;

            case 'host-name':
            case 'hostname':
                if (!$this->validateHostname($element)) {
                    $this->addError($path, "Invalid hostname");
                }
                break;

            default:
                $this->addError($path, "Unknown format: " . json_encode($schema->format));
                break;
        }
    }

    protected function validateDateTime($datetime, $format)
    {
        $dt = \DateTime::createFromFormat($format, $datetime);

        if (!$dt) {
            return false;
        }

        return $datetime === $dt->format($format);
    }

    protected function validateRegex($regex)
    {
        return false !== @preg_match('/' . $regex . '/', '');
    }

    protected function validateColor($color)
    {
        if (in_array(strtolower($color), array('aqua', 'black', 'blue', 'fuchsia',
            'gray', 'green', 'lime', 'maroon', 'navy', 'olive', 'orange', 'purple',
            'red', 'silver', 'teal', 'white', 'yellow'))) {
            return true;
        }

        return preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color);
    }

    protected function validateStyle($style)
    {
        $properties     = explode(';', rtrim($style, ';'));
        $invalidEntries = preg_grep('/^\s*[-a-z]+\s*:\s*.+$/i', $properties, PREG_GREP_INVERT);

        return empty($invalidEntries);
    }

    protected function validatePhone($phone)
    {
        return preg_match('/^\+?(\(\d{3}\)|\d{3}) \d{3} \d{4}$/', $phone);
    }

    public function validateUri($uri)
    {
        $scheme = null;
        $port = null;
        $userInfo = null;
        $host = null;
        $path = null;
        $fragment = null;
        $query = null;

        if (preg_match('/^([A-Za-z][A-Za-z0-9\.\+\-]*):/', $uri, $match)) {
            $scheme = $match[1];
        }

        if (($scheme) !== null) {
            $uri = substr($uri, strlen($scheme) + 1);
        }

        // Capture authority part
        if (preg_match('|^//([^/\?#]*)|', $uri, $match)) {
            $authority = $match[1];
            $uri       = substr($uri, strlen($match[0]));
            // Split authority into userInfo and host
            if (strpos($authority, '@') !== false) {
                // The userInfo can also contain '@' symbols; split $authority
                // into segments, and set it to the last segment.
                $segments  = explode('@', $authority);
                $authority = array_pop($segments);
                $userInfo  = implode('@', $segments);
                unset($segments);
            }
            $nMatches = preg_match('/:[\d]{1,5}$/', $authority, $matches);
            if ($nMatches === 1) {
                $portLength = strlen($matches[0]);
                $port = substr($matches[0], 1);
                $port = (int) $port;
                $authority = substr($authority, 0, -$portLength);
            }
            $host = $authority;
        }

        // Capture the path
        if ($uri && preg_match('|^[^\?#]*|', $uri, $match)) {
            $path = $match[0];
            $uri = substr($uri, strlen($match[0]));
        }

        // Capture the query
        if ($uri && preg_match('|^\?([^#]*)|', $uri, $match)) {
            $query = $match[1];
            $uri = substr($uri, strlen($match[0]));
        }

        // All that's left is the fragment
        if ($uri && substr($uri, 0, 1) == '#') {
            $fragment = substr($uri, 1);
        }

        if ($scheme === null) {
            $isRelative = true;
        } else {
            $isRelative = false;
        }

        if (!$isRelative) {
            if ($host) {
                if (strlen($path) > 0 && substr($path, 0, 1) != '/') {
                    return false;
                }
                return true;
            }
            if ($userInfo || $port) {
                return false;
            }
            if ($path) {
                // Check path-only (no host) URI
                if (substr($path, 0, 2) == '//') {
                    return false;
                }
                return true;
            }
            if (! ($query || $fragment)) {
                // No host, path, query or fragment - this is not a valid URI
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    protected function validateHostname($host)
    {
        return preg_match('/^[_a-z]+\.([_a-z]+\.?)+$/i', $host);
    }
}
