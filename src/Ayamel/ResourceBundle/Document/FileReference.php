<?php

namespace Ayamel\ResourceBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation as JMS;

/**
 * File reference object
 *
 * @MongoDB\EmbeddedDocument
 * @JMS\ExclusionPolicy("none")
 *
 */
class FileReference
{
    /**
     * A public URI where the file is accessible.
     *
     * @MongoDB\String
     * @JMS\SerializedName("downloadUri")
     * @JMS\Type("string")
     */
    protected $downloadUri;

    /**
     * A public URI where the file can be streamed from.
     *
     * @MongoDB\String
     * @JMS\SerializedName("streamUri")
     * @JMS\Type("string")
     */
    protected $streamUri;

    /**
     * @MongoDB\String
     * @JMS\Exclude
     */
    protected $internalUri;

    /**
     * Size of the file in bytes
     *
     * @MongoDB\Int
     * @JMS\Type("integer")
     */
    protected $bytes;

    /**
     * A string describing the representation.
     *
     * Valid values include:
     *
     * - **original** - If this is the original file.
     * - **transcoding** - If this file is a transcoding of the original in its entirety.
     * - **summary** - If this file is a partial transcoding of the original.
     *
     * Quality is an integer representing the relative quality.
     *
     * @MongoDB\String
     * @JMS\Type("string")
     */
    protected $representation;

    /**
     * An integer describing the relative quality.  Higher means higher quality relative to others.
     * Default quality is `1`.
     *
     * @MongoDB\Int
     * @JMS\Type("integer")
     */
    protected $quality;

    /**
     * The full mime string of the file, in as much detail as possible.  If not set, it will be set automatically
     * to the value of `mimeType`.
     *
     * @MongoDB\String
     * @JMS\Type("string")
     */
    protected $mime;

    /**
     * The short mime type of the file, no extra information.
     *
     * @MongoDB\String
     * @JMS\Type("string")
     * @JMS\SerializedName("mimeType")
     */
    protected $mimeType;

    /**
     * A key/val hash of attributes, relevant to the `mimeType` of the file.  For details on which attributes
     * are valid for a given mimeTime, please read through the documentation on the [project wiki](https://github.com/AmericanCouncils/AyamelResourceApiServer/wiki/Validation:-File-Attributes).
     *
     * @MongoDB\Hash
     * @JMS\Type("array")
     */
    protected $attributes;

    /**
     * Create a reference from an internal file path
     *
     * @param  string        $internalUri
     * @return FileReference
     */
    public static function createFromLocalPath($internalUri)
    {
        $ref = new static();
        $ref->setInternalUri($internalUri);

        return $ref;
    }

    /**
     * Create a reference to a public uri
     *
     * @param  string        $downloadUri
     * @return FileReference
     */
    public static function createFromDownloadUri($downloadUri)
    {
        $ref = new static();
        $ref->setDownloadUri($downloadUri);

        return $ref;
    }

    /**
     * Return whether or not the file is the original
     *
     * @param boolean $bool
     */
    public function isOriginal()
    {
        return ('original' === $this->representation);
    }

    /**
     * Get the string describing the files representation of the associated resource
     *
     * @return string
     */
    public function getRepresentation()
    {
        return $this->representation;
    }

    /**
     * Set the representation string, which can an be any of "original","summary", or "transcoding"
     *
     * @param string $representation
     */
    public function setRepresentation($representation)
    {
        $this->representation = $representation;
    }

    /**
     * Get the relative quality
     *
     * @return null|integer
     */
    public function getQuality()
    {
        return $this->quality;
    }

    /**
     * Set the relative quality
     *
     * @param int $quality
     */
    public function setQuality($quality)
    {
        $this->quality = $quality;
    }

    /**
     * Set all attributes
     *
     * @param hash $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get all attributes
     *
     * @return hash $attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Merge an array of attributes into the current set, this will overwrite conflicting keys
     * with the latest one received
     *
     * @param array $attrs
     */
    public function mergeAttributes(array $attrs)
    {
        $this->attributes = array_merge($this->attributes, $attrs);
    }

    /**
     * Set an individual attribute by key for the attributes propery.
     *
     * @param  string $key
     * @param  mixed  $val
     * @return self
     */
    public function setAttribute($key, $val)
    {
        $this->attributes[$key] = $val;

        return $this;
    }

    /**
     * Get an individual attribute by key, returns default value if not found
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : $default;
    }

    /**
     * Remove an attribute by key if it exists.
     *
     * @param  string $key
     * @return self
     */
    public function removeAttribute($key)
    {
        if (isset($this->attributes[$key])) {
            unset($this->attributes[$key]);
        }

        return $this;
    }

    /**
     * Return boolean if attribute exists
     *
     * @param  string  $key
     * @return boolean
     */
    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Set file size in bytes
     *
     * @param integer $bytes
     */
    public function setBytes($bytes)
    {
        $this->bytes = $bytes;
    }

    /**
     * Get file size in bytes
     *
     * @return integer
     */
    public function getBytes()
    {
        return $this->bytes;
    }

    /**
     * Set the mime string
     *
     * @param string $mime
     */
    public function setMime($mime)
    {
        $this->mime = $mime;
    }

    /**
     * Returns mime string
     *
     * @return string
     */
    public function getMime()
    {
        return $this->mime;
    }

    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Set downloadUri
     *
     * @param string $downloadUri
     */
    public function setDownloadUri($downloadUri)
    {
        $this->downloadUri = $downloadUri;
    }

    /**
     * Get downloadUri
     *
     * @return string $downloadUri
     */
    public function getDownloadUri()
    {
        return $this->downloadUri;
    }

    /**
     * Set streamUri
     *
     * @param string $streamUri
     */
    public function setStreamUri($streamUri)
    {
        $this->streamUri = $streamUri;
    }

    /**
     * Get streamUri
     *
     * @return string $streamUri
     */
    public function getStreamUri()
    {
        return $this->streamUri;
    }

    /**
     * Set internalUri
     *
     * @param string $internalUri
     */
    public function setInternalUri($internalUri)
    {
        $this->internalUri = $internalUri;
    }

    /**
     * Get internalUri
     *
     * @return string $internalUri
     */
    public function getInternalUri()
    {
        return $this->internalUri;
    }

    /**
     * Enforces certain values before persisting to the database
     *
     * @MongoDB\PrePersist
     * @MongoDB\PreUpdate
     */
    public function validate()
    {
        if (null === $this->quality) {
            $this->quality = 0;
        }

        if (null === $this->mime) {
            $this->mime = $this->mimeType;
        }
    }

    /**
     * Test if a given file reference instance is pointing to the same file as this file reference instance.
     *
     * @param  FileReference $file
     * @return boolean
     */
    public function equals(FileReference $file)
    {
        if (($file->getInternalUri() && $this->getInternalUri()) && ($file->getInternalUri() == $this->getInternalUri())) {
            return true;
        }

        if (($file->getDownloadUri() && $this->getDownloadUri()) && ($file->getDownloadUri() == $this->getDownloadUri())) {
            return true;
        }

        return false;
    }
}
