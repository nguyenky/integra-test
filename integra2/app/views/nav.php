<?php
$groups = [];

foreach ($acl as $a)
{
    if (!array_key_exists($a['parent'], $groups))
        $groups[$a['parent']] = ['icon' => $a['icon'], 'items' => []];

    $groups[$a['parent']]['items'][] = ['url' => $a['url'], 'title' => $a['title']];
}
?>

<navigation>

<?php foreach ($groups as $parent => $group): ?>
    <nav:group data-icon="fa fa-lg fa-fw <?=$group['icon']?>" title="<?=$parent?>">
    <?php foreach ($group['items'] as $item): ?>
        <nav:item data-view="<?= $item['url'] ?>" title="<?=$item['title']?>" />
    <?php endforeach; ?>
    </nav:group>
<?php endforeach; ?>

</navigation>
<span class="minifyme" data-action="minifyMenu"> <i class="fa fa-arrow-circle-left hit"></i> </span>