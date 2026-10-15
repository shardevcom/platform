<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Doctor;

/**
 * Type of security finding.
 */
enum FindingType: string
{
    case ConfirmedVulnerability = 'vulnerability';
    case PotentialRisk = 'risk';
    case InsecureConfiguration = 'config';
    case PreventiveAdvice = 'advice';
}