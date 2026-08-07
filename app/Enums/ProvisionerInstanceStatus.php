<?php

namespace App\Enums;

/**
 * The provisioner's own three-state model for an instance's status check
 * (specs/whatsapp-workspace-provisioning/spec.md: "active, unhealthy, or
 * absent"). Distinct from `WaInstanceState`, which tracks WakuWaku's local
 * bookkeeping for a `wa_instances` row rather than a live status query.
 */
enum ProvisionerInstanceStatus: string
{
    case Active = 'active';
    case Unhealthy = 'unhealthy';
    case Absent = 'absent';
}
