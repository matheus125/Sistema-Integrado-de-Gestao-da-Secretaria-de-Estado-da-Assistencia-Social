<?php

return [
    'modules' => [
        'dashboard' => ['name' => 'Dashboard', 'icon' => 'dashboard'],
        'administracao' => ['name' => 'Administracao', 'icon' => 'admin_panel_settings'],
        'transporte' => ['name' => 'Gerencia de Transporte', 'icon' => 'directions_car'],
        'materiais-patrimonio' => ['name' => 'Materiais e Patrimonio', 'icon' => 'inventory_2'],
        'informatica' => ['name' => 'Gerencia de Informatica', 'icon' => 'support_agent'],
        'relatorios' => ['name' => 'Relatorios', 'icon' => 'summarize'],
        'auditoria' => ['name' => 'Auditoria', 'icon' => 'manage_search'],
        'configuracoes' => ['name' => 'Configuracoes', 'icon' => 'settings'],
    ],

    'actions' => [
        'visualizar',
        'cadastrar',
        'editar',
        'excluir',
        'aprovar',
        'cancelar',
        'exportar',
        'emitir-pdf',
    ],
];
