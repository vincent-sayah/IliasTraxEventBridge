<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('evnt_evhk_itxeb_log')) {
    $ilDB->createTable('evnt_evhk_itxeb_log', [
        'id' => ['type' => 'integer', 'length' => 8, 'notnull' => true],
        'component' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'event_name' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'user_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'ref_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_type' => ['type' => 'text', 'length' => 64, 'notnull' => true, 'default' => ''],
        'param_keys' => ['type' => 'clob', 'notnull' => false],
        'payload_json' => ['type' => 'clob', 'notnull' => false],
        'request_uri' => ['type' => 'clob', 'notnull' => false],
        'http_method' => ['type' => 'text', 'length' => 16, 'notnull' => true, 'default' => ''],
        'created_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'created_ts' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('evnt_evhk_itxeb_log', ['id']);
    $ilDB->createSequence('evnt_evhk_itxeb_log');
    $ilDB->addIndex('evnt_evhk_itxeb_log', ['component'], 'i1');
    $ilDB->addIndex('evnt_evhk_itxeb_log', ['event_name'], 'i2');
    $ilDB->addIndex('evnt_evhk_itxeb_log', ['user_id'], 'i3');
    $ilDB->addIndex('evnt_evhk_itxeb_log', ['ref_id'], 'i4');
    $ilDB->addIndex('evnt_evhk_itxeb_log', ['created_ts'], 'i5');
}
?>
<#2>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('evnt_evhk_itxeb_out')) {
    $ilDB->createTable('evnt_evhk_itxeb_out', [
        'id' => ['type' => 'integer', 'length' => 8, 'notnull' => true],
        'event_log_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'statement_uuid' => ['type' => 'text', 'length' => 36, 'notnull' => true, 'default' => ''],
        'event_type' => ['type' => 'text', 'length' => 64, 'notnull' => true, 'default' => ''],
        'verb_id' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'user_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'ref_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_type' => ['type' => 'text', 'length' => 64, 'notnull' => true, 'default' => ''],
        'statement_json' => ['type' => 'clob', 'notnull' => false],
        'status' => ['type' => 'text', 'length' => 32, 'notnull' => true, 'default' => 'generated'],
        'created_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'created_ts' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'sent_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'last_error' => ['type' => 'clob', 'notnull' => false],
    ]);
    $ilDB->addPrimaryKey('evnt_evhk_itxeb_out', ['id']);
    $ilDB->createSequence('evnt_evhk_itxeb_out');
    $ilDB->addIndex('evnt_evhk_itxeb_out', ['event_log_id'], 'i1');
    $ilDB->addIndex('evnt_evhk_itxeb_out', ['statement_uuid'], 'i2');
    $ilDB->addIndex('evnt_evhk_itxeb_out', ['event_type'], 'i3');
    $ilDB->addIndex('evnt_evhk_itxeb_out', ['status'], 'i4');
    $ilDB->addIndex('evnt_evhk_itxeb_out', ['created_ts'], 'i5');
}
?>
<#3>
<?php
/** @var ilDBInterface $ilDB */
if ($ilDB->tableExists('evnt_evhk_itxeb_out')) {
    if (!$ilDB->tableColumnExists('evnt_evhk_itxeb_out', 'retry_count')) {
        $ilDB->addTableColumn('evnt_evhk_itxeb_out', 'retry_count', [
            'type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0,
        ]);
    }
    if (!$ilDB->tableColumnExists('evnt_evhk_itxeb_out', 'max_retry')) {
        $ilDB->addTableColumn('evnt_evhk_itxeb_out', 'max_retry', [
            'type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 5,
        ]);
    }
    if (!$ilDB->tableColumnExists('evnt_evhk_itxeb_out', 'last_attempt_at')) {
        $ilDB->addTableColumn('evnt_evhk_itxeb_out', 'last_attempt_at', [
            'type' => 'text', 'length' => 19, 'notnull' => true, 'default' => '',
        ]);
    }
    $ilDB->manipulate('UPDATE evnt_evhk_itxeb_out SET max_retry = 5 WHERE max_retry = 0');
}
?>
<#4>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('evnt_evhk_itxeb_read')) {
    $ilDB->createTable('evnt_evhk_itxeb_read', [
        'obj_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'usr_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'last_access' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'read_count' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'processed_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
    ]);
    $ilDB->addPrimaryKey('evnt_evhk_itxeb_read', ['obj_id', 'usr_id']);
    $ilDB->addIndex('evnt_evhk_itxeb_read', ['last_access'], 'i1');
    $ilDB->addIndex('evnt_evhk_itxeb_read', ['usr_id'], 'i2');
}
?>
<#5>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('evnt_evhk_itxeb_ccfg')) {
    $ilDB->createTable('evnt_evhk_itxeb_ccfg', [
        'course_ref_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'course_obj_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'enabled' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
        'created_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'updated_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'updated_by' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('evnt_evhk_itxeb_ccfg', ['course_ref_id']);
    $ilDB->addIndex('evnt_evhk_itxeb_ccfg', ['course_obj_id'], 'i1');
    $ilDB->addIndex('evnt_evhk_itxeb_ccfg', ['enabled'], 'i2');
    $ilDB->addIndex('evnt_evhk_itxeb_ccfg', ['updated_by'], 'i3');
}

if (!$ilDB->tableExists('evnt_evhk_itxeb_rcfg')) {
    $ilDB->createTable('evnt_evhk_itxeb_rcfg', [
        'course_ref_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'ref_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_id' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'obj_type' => ['type' => 'text', 'length' => 64, 'notnull' => true, 'default' => ''],
        'enabled' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
        'created_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'updated_at' => ['type' => 'text', 'length' => 19, 'notnull' => true, 'default' => ''],
        'updated_by' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('evnt_evhk_itxeb_rcfg', ['course_ref_id', 'ref_id']);
    $ilDB->addIndex('evnt_evhk_itxeb_rcfg', ['obj_id'], 'i1');
    $ilDB->addIndex('evnt_evhk_itxeb_rcfg', ['obj_type'], 'i2');
    $ilDB->addIndex('evnt_evhk_itxeb_rcfg', ['enabled'], 'i3');
}
?>