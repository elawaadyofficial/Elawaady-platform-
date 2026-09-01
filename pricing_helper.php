<?php
function service_unit_total(array $svc, int $qty): float {
    $mode=$svc['price_mode'] ?? 'manual';
    if ($mode==='provider_auto' && (float)($svc['provider_base_price'] ?? 0)>0) {
        $per=max(1,(int)($svc['provider_price_per'] ?? 1000));
        $base=((float)$svc['provider_base_price']*$qty)/$per;
        $profit=max(0,(float)($svc['profit_percent'] ?? 0));
        return round($base*(1+$profit/100),2);
    }
    return round((float)($svc['price'] ?? 0)*$qty,2);
}
?>