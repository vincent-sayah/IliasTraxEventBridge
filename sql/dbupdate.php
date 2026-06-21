<#1>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('evnt_evhk_itxeb_log')) {
    $ilDB->createTable('evnt_evhk_itxeb_log', [
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'component' => [
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ],
        'event_name' => [
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ],
        'user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'ref_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'obj_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'obj_type' => [
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
            'default' => ''
        ],
        'param_keys' => [
            'type' => 'clob',
            'notnull' => false
        ],
        'payload_json' => [
            'type' => 'clob',
            'notnull' => false
        ],
        'request_uri' => [
            'type' => 'clob',
            'notnull' => false
        ],
        'http_method' => [
            'type' => 'text',
            'length' => 16,
            'notnull' => true,
            'default' => ''
        ],
        'created_at' => [
            'type' => 'text',
            'length' => 19,
            'notnull' => true,
            'default' => ''
        ],
        'created_ts' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ]
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
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ],
        'event_log_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'statement_uuid' => [
            'type' => 'text',
            'length' => 36,
            'notnull' => true,
            'default' => ''
        ],
        'event_type' => [
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
            'default' => ''
        ],
        'verb_id' => [
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ],
        'user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'ref_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'obj_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'obj_type' => [
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
            'default' => ''
        ],
        'statement_json' => [
            'type' => 'clob',
            'notnull' => false
        ],
        'status' => [
            'type' => 'text',
            'length' => 32,
            'notnull' => true,
            'default' => 'generated'
        ],
        'created_at' => [
            'type' => 'text',
            'length' => 19,
            'notnull' => true,
            'default' => ''
        ],
        'created_ts' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ],
        'sent_at' => [
            'type' => 'text',
            'length' => 19,
            'notnull' => true,
            'default' => ''
        ],
        'last_error' => [
            'type' => 'clob',
            'notnull' => false
        ]
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
