<?php
$HEARTBEAT_JS = <<<'JS'
<script>
// Presence heartbeat — update last_active setiap 2 menit
(function() {
    function hb() {
        fetch('../heartbeat.php', {method:'POST', credentials:'same-origin'})
            .catch(function(){});
    }
    hb();
    setInterval(hb, 120000);
})();
</script>
JS;

$HEARTBEAT_JS_SAME = <<<'JS'
<script>
(function() {
    function hb() {
        fetch('heartbeat.php', {method:'POST', credentials:'same-origin'})
            .catch(function(){});
    }
    hb();
    setInterval(hb, 120000);
})();
</script>
JS;
?>
