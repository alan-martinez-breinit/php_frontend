<?php

/**
 * Sanitiza fragmentos HTML de columnas con html=>true (defense-in-depth).
 * El control primario es la CSP; aquí se eliminan vectores de inyección obvios
 * (scripts, iframes, handlers de evento, URIs javascript:) por si el backend
 * algún día devuelve HTML no confiable. No es un sanitizador completo.
 */
function sanitizeHtmlFragment(string $html): string
{
    $html = preg_replace('#<(script|iframe|object|embed|style|link|meta)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|iframe|object|embed|style|link|meta)[^>]*/?>#is', '', $html);
    $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', '$1="#"', $html);
    return $html;
}

function renderTabla(array $params): void
{
    $cols       = $params['columnas'] ?? [];
    $filas      = $params['filas'] ?? [];
    $totales    = $params['totales'] ?? null;
    $claseTbl   = $params['clase_tabla'] ?? '';
    $claseTfoot = $params['clase_tfoot'] ?? 'tabla-tfoot';
    $vacio      = $params['vacio'] ?? 'Sin registros.';
    $click      = !empty($params['click']);
    $numCols    = count($cols);

    if (empty($cols)) return;
?>
    <div class="tabla-wrap">
        <table class="tabla <?= htmlspecialchars($claseTbl) ?>">
            <thead>
                <tr>
                    <?php foreach ($cols as $col):
                        $fmt  = isset($col['formato']) ? ' data-col-formato="' . htmlspecialchars($col['formato']) . '"' : '';
                        $form = isset($col['formula']) ? ' data-col-formula="' . htmlspecialchars($col['formula']) . '"' : '';
                    ?>
                        <th data-col-id="<?= htmlspecialchars($col['id']) ?>" <?= $fmt ?><?= $form ?><?= !empty($col['clase']) ? ' class="' . htmlspecialchars($col['clase']) . '"' : '' ?>><?= htmlspecialchars($col['label'] ?? $col['id']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filas)): ?>
                    <tr>
                        <td class="tabla-vacio" colspan="<?= $numCols ?>"><?= htmlspecialchars($vacio) ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filas as $fila):
                        if (!is_array($fila)) continue;
                    ?>
                        <tr<?= $click ? ' style="cursor:pointer"' : '' ?>>
                            <?php foreach ($cols as $col):
                                $id   = $col['id'];
                                $raw  = $fila[$id] ?? null;
                                $mostrar = ($raw !== null && $raw !== '') ? (string)$raw : '—';
                                $clase = $col['clase'] ?? '';
                                $esHtml = !empty($col['html']);

                                if (!empty($col['clase_campo'])) {
                                    $claseExtra = $fila[$col['clase_campo']] ?? '';
                                    if ($claseExtra !== '') {
                                        $clase = trim($clase . ' ' . $claseExtra);
                                    }
                                }

                                $attrData = '';
                                if (!empty($col['raw_id'])) {
                                    $rv = $fila[$col['raw_id']] ?? null;
                                    if (is_numeric($rv)) {
                                        $attrData = ' data-raw="' . htmlspecialchars((string)(float)$rv) . '"';
                                    }
                                }
                            ?>
                                <td class="<?= $clase ?>" data-campo="<?= htmlspecialchars($id) ?>" <?= $attrData ?>><?= $esHtml ? sanitizeHtmlFragment($mostrar) : htmlspecialchars($mostrar) ?></td>
                            <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
            </tbody>
            <?php if ($totales !== null && is_array($totales) && !empty($filas)): ?>
                <tfoot>
                    <tr class="<?= htmlspecialchars($claseTfoot) ?>">
                        <?php foreach ($cols as $col):
                            $id   = $col['id'];
                            $raw  = $totales[$id] ?? null;
                            $mostrar = ($raw !== null && $raw !== '') ? (string)$raw : '—';
                            $clase = $col['clase'] ?? '';
                            $esHtml = !empty($col['html']);

                            if (!empty($col['clase_campo'])) {
                                $claseExtra = $totales[$col['clase_campo']] ?? '';
                                if ($claseExtra !== '') {
                                    $clase = trim($clase . ' ' . $claseExtra);
                                }
                            }
                        ?>
                            <td class="<?= $clase ?>" data-campo="<?= htmlspecialchars($id) ?>"><?= $esHtml ? sanitizeHtmlFragment($mostrar) : htmlspecialchars($mostrar) ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
<?php
}
