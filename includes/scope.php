<?php
declare(strict_types=1);

/**
 * @param array{id:int,role:string,company_id:?int,warehouse_id:?int} $u
 * @return array{0: string, 1: array<int, mixed>} SQL fragment and params for company/warehouse scope on transactions/tools/operators
 */
function tm_sql_scope_transactions(array $u): array
{
    if ($u['role'] === 'super_admin') {
        return ['1=1', []];
    }
    if ($u['role'] === 'admin' && $u['company_id']) {
        return ['tr.company_id = ?', [$u['company_id']]];
    }
    if ($u['role'] === 'manager' && $u['warehouse_id']) {
        return ['tr.warehouse_id = ?', [$u['warehouse_id']]];
    }
    return ['1=0', []];
}

/** Scope for tools table alias t */
function tm_sql_scope_tools_catalog(array $u): array
{
    if ($u['role'] === 'super_admin') {
        return ['1=1', []];
    }
    if ($u['role'] === 'admin' && $u['company_id']) {
        return ['t.company_id = ?', [$u['company_id']]];
    }
    if ($u['role'] === 'manager' && $u['company_id'] && $u['warehouse_id']) {
        return [
            't.company_id = ? AND EXISTS (SELECT 1 FROM tool_warehouse_assignment twa2 WHERE twa2.tool_id = t.id AND twa2.warehouse_id = ? AND twa2.deleted_flag = 0)',
            [$u['company_id'], $u['warehouse_id']],
        ];
    }
    return ['1=0', []];
}

/** Operators list scope */
function tm_sql_scope_operators(array $u): array
{
    if ($u['role'] === 'super_admin') {
        return ['1=1', []];
    }
    if ($u['role'] === 'admin' && $u['company_id']) {
        return ['o.company_id = ?', [$u['company_id']]];
    }
    if ($u['role'] === 'manager' && $u['warehouse_id']) {
        return ['o.warehouse_id = ?', [$u['warehouse_id']]];
    }
    return ['1=0', []];
}
