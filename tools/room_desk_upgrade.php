<?php

function room_desk_upgrade_token(): string
{
    global $User;

    // Infosphère authenticates with cookies, without a native PHP session.
    if (!isset($User['id']) || !is_string($_COOKIE['password'] ?? null) || $_COOKIE['password'] === '')
        return '';
    return hash_hmac('sha256', 'room-desk-upgrade/' . (int)$User['id'], $_COOKIE['password']);
}

function room_desk_upgrade_states(array $desks): array
{
    global $Dictionnary;

    if ($desks === [])
        return [];
    $response = hand_request(['command' => 'get_machine_upgrades'], false, 5);
    $available = ($response['result'] ?? '') === 'ok' && is_array($response['machines'] ?? null);
    $labels = [
        'ready' => 'RoomDeskUpgradeReady',
        'pending' => 'RoomDeskUpgradePending',
        'running' => 'RoomDeskUpgradeRunning',
        'succeeded' => 'RoomDeskUpgradeSucceeded',
        'failed' => 'RoomDeskUpgradeFailed',
        'expired' => 'RoomDeskUpgradeExpired',
        'offline' => 'RoomDeskUpgradeOffline',
        'unsupported' => 'RoomDeskUpgradeUnsupported',
        'unavailable' => 'RoomDeskUpgradeUnavailable',
    ];
    $states = [];
    foreach ($desks as $desk)
    {
        $mac = strtolower(str_replace([':', '-', '.'], '', (string)($desk['mac'] ?? '')));
        $mac = implode(':', str_split($mac, 2));
        $machine = $available ? ($response['machines'][$mac] ?? []) : [];
        $upgrade = $machine['upgrade'] ?? [];
        $status = $upgrade['status'] ?? 'ready';
        if (!isset($labels[$status]))
            $status = 'unavailable';
        if (!$available)
            $status = 'unavailable';
        else if (!in_array($status, ['pending', 'running'], true))
        {
            if (empty($machine['online']))
                $status = 'offline';
            else if (empty($machine['supported']))
                $status = 'unsupported';
        }
        $states[(int)$desk['id']] = [
            'status' => $status,
            'label' => $Dictionnary[$labels[$status]],
            'can_request' => room_desk_can_work_on_room($desk['id_room'])
                && !empty($machine['online']) && !empty($machine['supported'])
                && !in_array($status, ['pending', 'running', 'unavailable'], true),
            'huile_version' => ($desk['huile_version'] ?? '') ?: '—',
        ];
    }
    return $states;
}
