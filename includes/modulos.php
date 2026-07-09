<?php
function modulosPorCliente(string $codigoCliente): array
{
    $modulos = [
        "0003" => [
            ["slug" => "cxc",                        "label" => "CXC",                       "icon" => "payments"],
            ["slug" => "industria",                  "label" => "Industria",                 "icon" => "factory"],
            ["slug" => "inventario-autos",           "label" => "Inventario Autos",          "icon" => "garage"],
            ["slug" => "inventario-refacciones",     "label" => "Inventario Refacciones",    "icon" => "inventory_2"],
            ["slug" => "objetivo-autos",             "label" => "Objetivo Autos",            "icon" => "track_changes"],
            ["slug" => "objetivos-servicio",         "label" => "Objetivos Servicio",        "icon" => "handyman"],
            ["slug" => "ventas-autos",              "label" => "Ventas Autos",              "icon" => "directions_car"],
            ["slug" => "venta-servicio-refacciones", "label" => "Venta Servicio Refacciones", "icon" => "build_circle"],
        ],
        "0579" => [
            ["slug" => "cxc",                        "label" => "CXC",                       "icon" => "payments"],
            ["slug" => "finanzas",                   "label" => "Finanzas",                  "icon" => "account_balance"],
            ["slug" => "industria",                  "label" => "Industria",                 "icon" => "factory"],
            ["slug" => "inventario-autos",           "label" => "Inventario Autos",          "icon" => "garage"],
            ["slug" => "inventario-refacciones",     "label" => "Inventario Refacciones",    "icon" => "inventory_2"],
            ["slug" => "objetivo-autos",             "label" => "Objetivo Autos",            "icon" => "track_changes"],
            ["slug" => "objetivos-servicio",         "label" => "Objetivos Servicio",        "icon" => "handyman"],
            ["slug" => "ventas-autos",              "label" => "Ventas Autos",              "icon" => "directions_car"],
            ["slug" => "venta-servicio-refacciones", "label" => "Venta Servicio Refacciones", "icon" => "build_circle"],
        ],
    ];

    return $modulos[$codigoCliente] ?? [];
}
