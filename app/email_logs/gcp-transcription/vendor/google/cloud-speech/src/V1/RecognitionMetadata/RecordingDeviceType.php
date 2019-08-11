<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/cloud/speech/v1/cloud_speech.proto

namespace Google\Cloud\Speech\V1\RecognitionMetadata;

use UnexpectedValueException;

/**
 * The type of device the speech was recorded with.
 *
 * Protobuf type <code>google.cloud.speech.v1.RecognitionMetadata.RecordingDeviceType</code>
 */
class RecordingDeviceType
{
    /**
     * The recording device is unknown.
     *
     * Generated from protobuf enum <code>RECORDING_DEVICE_TYPE_UNSPECIFIED = 0;</code>
     */
    const RECORDING_DEVICE_TYPE_UNSPECIFIED = 0;
    /**
     * Speech was recorded on a smartphone.
     *
     * Generated from protobuf enum <code>SMARTPHONE = 1;</code>
     */
    const SMARTPHONE = 1;
    /**
     * Speech was recorded using a personal computer or tablet.
     *
     * Generated from protobuf enum <code>PC = 2;</code>
     */
    const PC = 2;
    /**
     * Speech was recorded over a phone line.
     *
     * Generated from protobuf enum <code>PHONE_LINE = 3;</code>
     */
    const PHONE_LINE = 3;
    /**
     * Speech was recorded in a vehicle.
     *
     * Generated from protobuf enum <code>VEHICLE = 4;</code>
     */
    const VEHICLE = 4;
    /**
     * Speech was recorded outdoors.
     *
     * Generated from protobuf enum <code>OTHER_OUTDOOR_DEVICE = 5;</code>
     */
    const OTHER_OUTDOOR_DEVICE = 5;
    /**
     * Speech was recorded indoors.
     *
     * Generated from protobuf enum <code>OTHER_INDOOR_DEVICE = 6;</code>
     */
    const OTHER_INDOOR_DEVICE = 6;

    private static $valueToName = [
        self::RECORDING_DEVICE_TYPE_UNSPECIFIED => 'RECORDING_DEVICE_TYPE_UNSPECIFIED',
        self::SMARTPHONE => 'SMARTPHONE',
        self::PC => 'PC',
        self::PHONE_LINE => 'PHONE_LINE',
        self::VEHICLE => 'VEHICLE',
        self::OTHER_OUTDOOR_DEVICE => 'OTHER_OUTDOOR_DEVICE',
        self::OTHER_INDOOR_DEVICE => 'OTHER_INDOOR_DEVICE',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(RecordingDeviceType::class, \Google\Cloud\Speech\V1\RecognitionMetadata_RecordingDeviceType::class);

