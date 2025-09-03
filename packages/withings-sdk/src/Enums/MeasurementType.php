<?php

namespace Filipac\Withings\Enums;

enum MeasurementType: int
{
    case WEIGHT = 1;
    case HEIGHT = 4;
    case FAT_FREE_MASS = 5;
    case FAT_RATIO = 6;
    case FAT_MASS_WEIGHT = 8;
    case DIASTOLIC_BLOOD_PRESSURE = 9;
    case SYSTOLIC_BLOOD_PRESSURE = 10;
    case HEART_PULSE = 11;
    case TEMPERATURE = 12;
    case SP02 = 54;
    case BODY_TEMPERATURE = 71;
    case SKIN_TEMPERATURE = 73;
    case MUSCLE_MASS = 76;
    case HYDRATION = 77;
    case BONE_MASS = 88;
    case PULSE_WAVE_VELOCITY = 91;
    case VO2_MAX = 123;
    case QRS_INTERVAL_DURATION = 135;
    case PR_INTERVAL_DURATION = 136;
    case QT_INTERVAL_DURATION = 137;
    case CORRECTED_QT_INTERVAL_DURATION = 138;
    case ATRIAL_FIBRILLATION_RESULT = 139;

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match ($this) {
            self::WEIGHT => 'Weight',
            self::HEIGHT => 'Height',
            self::FAT_FREE_MASS => 'Fat Free Mass',
            self::FAT_RATIO => 'Fat Ratio',
            self::FAT_MASS_WEIGHT => 'Fat Mass Weight',
            self::DIASTOLIC_BLOOD_PRESSURE => 'Diastolic Blood Pressure',
            self::SYSTOLIC_BLOOD_PRESSURE => 'Systolic Blood Pressure',
            self::HEART_PULSE => 'Heart Pulse',
            self::TEMPERATURE => 'Temperature',
            self::SP02 => 'SP02',
            self::BODY_TEMPERATURE => 'Body Temperature',
            self::SKIN_TEMPERATURE => 'Skin Temperature',
            self::MUSCLE_MASS => 'Muscle Mass',
            self::HYDRATION => 'Hydration',
            self::BONE_MASS => 'Bone Mass',
            self::PULSE_WAVE_VELOCITY => 'Pulse Wave Velocity',
            self::VO2_MAX => 'VO2 max',
            self::QRS_INTERVAL_DURATION => 'QRS interval duration based on ECG signal',
            self::PR_INTERVAL_DURATION => 'PR interval duration based on ECG signal',
            self::QT_INTERVAL_DURATION => 'QT interval duration based on ECG signal',
            self::CORRECTED_QT_INTERVAL_DURATION => 'Corrected QT interval duration based on ECG signal',
            self::ATRIAL_FIBRILLATION_RESULT => 'Atrial fibrillation result from PPG',
        };
    }

    /**
     * Get measurement unit
     */
    public function getUnit(): string
    {
        return match ($this) {
            self::WEIGHT, self::FAT_FREE_MASS, self::FAT_MASS_WEIGHT,
            self::MUSCLE_MASS, self::BONE_MASS => 'kg',
            self::HEIGHT => 'm',
            self::FAT_RATIO, self::HYDRATION, self::SP02 => '%',
            self::DIASTOLIC_BLOOD_PRESSURE, self::SYSTOLIC_BLOOD_PRESSURE => 'mmHg',
            self::HEART_PULSE => 'bpm',
            self::TEMPERATURE, self::BODY_TEMPERATURE, self::SKIN_TEMPERATURE => '°C',
            self::PULSE_WAVE_VELOCITY => 'm/s',
            self::VO2_MAX => 'ml/min/kg',
            self::QRS_INTERVAL_DURATION, self::PR_INTERVAL_DURATION,
            self::QT_INTERVAL_DURATION, self::CORRECTED_QT_INTERVAL_DURATION => 'ms',
            self::ATRIAL_FIBRILLATION_RESULT => '',
        };
    }

    /**
     * Get commonly used measurement type groups
     */
    public static function getBodyCompositionTypes(): array
    {
        return [
            self::FAT_FREE_MASS,
            self::FAT_RATIO,
            self::FAT_MASS_WEIGHT,
            self::MUSCLE_MASS,
            self::HYDRATION,
            self::BONE_MASS,
        ];
    }

    public static function getVitalSignsTypes(): array
    {
        return [
            self::DIASTOLIC_BLOOD_PRESSURE,
            self::SYSTOLIC_BLOOD_PRESSURE,
            self::HEART_PULSE,
        ];
    }

    public static function getTemperatureTypes(): array
    {
        return [
            self::TEMPERATURE,
            self::BODY_TEMPERATURE,
            self::SKIN_TEMPERATURE,
        ];
    }

    public static function getEcgTypes(): array
    {
        return [
            self::QRS_INTERVAL_DURATION,
            self::PR_INTERVAL_DURATION,
            self::QT_INTERVAL_DURATION,
            self::CORRECTED_QT_INTERVAL_DURATION,
            self::ATRIAL_FIBRILLATION_RESULT,
        ];
    }

    public static function getAllTypes(): array
    {
        return [
            self::WEIGHT,
            self::HEIGHT,
            self::FAT_FREE_MASS,
            self::FAT_RATIO,
            self::FAT_MASS_WEIGHT,
            self::DIASTOLIC_BLOOD_PRESSURE,
            self::SYSTOLIC_BLOOD_PRESSURE,
            self::HEART_PULSE,
            self::TEMPERATURE,
            self::SP02,
            self::BODY_TEMPERATURE,
            self::SKIN_TEMPERATURE,
            self::MUSCLE_MASS,
            self::HYDRATION,
            self::BONE_MASS,
            self::PULSE_WAVE_VELOCITY,
            self::VO2_MAX,
            self::QRS_INTERVAL_DURATION,
            self::PR_INTERVAL_DURATION,
            self::QT_INTERVAL_DURATION,
            self::CORRECTED_QT_INTERVAL_DURATION,
            self::ATRIAL_FIBRILLATION_RESULT,
        ];
    }
}
