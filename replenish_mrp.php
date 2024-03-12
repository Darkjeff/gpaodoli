<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       gpaodoli/gpaodoliindex.php
 *	\ingroup    gpaodoli
 *	\brief      Home page of gpaodoli top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

// Load translation files required by the page
$langs->loadLangs(array("gpaodoli@gpaodoli",'products', 'stocks','mrp'));

$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}
$result = restrictedArea($user, 'produit|service');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('replenish_mrp'));

//checks if a product has been ordered


$action = GETPOST('action', 'aZ09');
$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$sall = trim((GETPOST('search_all', 'alphanohtml') != '') ?GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml'));
$type = GETPOST('type', 'int');
$salert = GETPOST('salert', 'alpha');
$includeproductswithoutdesiredqty = GETPOST('includeproductswithoutdesiredqty', 'alpha');
$mode = GETPOST('mode', 'alpha');

$order_id = GETPOST('order_id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');


$now = dol_now();

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$offset = $limit * $page;

if (!$sortfield) {
	$sortfield = 'p.ref';
}

if (!$sortorder) {
	$sortorder = 'ASC';
}

// Define virtualdiffersfromphysical
$virtualdiffersfromphysical = 0;
if (!empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT)
	|| !empty($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER)
	|| !empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)
	|| !empty($conf->global->STOCK_CALCULATE_ON_RECEPTION)
	|| !empty($conf->global->STOCK_CALCULATE_ON_RECEPTION_CLOSE)
	|| isModEnabled('mrp')) {
	$virtualdiffersfromphysical = 1; // According to increase/decrease stock options, virtual and physical stock may differs.
}


if ($virtualdiffersfromphysical) {
	$usevirtualstock = empty($conf->global->STOCK_USE_REAL_STOCK_BY_DEFAULT_FOR_REPLENISHMENT) ? 1 : 0;
} else {
	$usevirtualstock = 0;
}
if ($mode == 'physical') {
	$usevirtualstock = 0;
}
if ($mode == 'virtual') {
	$usevirtualstock = 1;
}



$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * Actions
 */

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // Both test are required to be compatible with all browsers
	$search_ref = '';
	$search_label = '';
	$sall = '';
	$salert = '';
	$includeproductswithoutdesiredqty = '';
	//$draftorder = '';
}

if ($action == 'of' && GETPOST('valid')) {
	$linecount = GETPOST('linecount', 'int');
	$box = 0;
	$errorQty = 0;
	unset($_POST['linecount']);
	if ($linecount > 0) {
		$db->begin();
		//TODO Create OF
	}
}


/*
 * View
 */

$form = new Form($db);
$formproduct = new FormProduct($db);
$prod = new Product($db);

$title = $langs->trans('gpaoDoliMenuProd');


if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$sqldesiredtock = $db->ifsql("pse.desiredstock IS NULL", "p.desiredstock", "pse.desiredstock");
	$sqlalertstock = $db->ifsql("pse.seuil_stock_alerte IS NULL", "p.seuil_stock_alerte", "pse.seuil_stock_alerte");
} else {
	$sqldesiredtock = 'p.desiredstock';
	$sqlalertstock = 'p.seuil_stock_alerte';
}

$sql = 'SELECT p.rowid, p.ref, p.label, p.description, p.price,';
$sql .= ' p.price_ttc, p.price_base_type, p.fk_product_type,';
$sql .= ' p.tms as datem, p.duration, p.tobuy,';
$sql .= ' p.desiredstock, p.seuil_stock_alerte,';
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$sql .= ' pse.desiredstock as desiredstockpse, pse.seuil_stock_alerte as seuil_stock_alertepse,';
}
$sql .= " ".$sqldesiredtock." as desiredstockcombined, ".$sqlalertstock." as seuil_stock_alertecombined,";
$sql .= ' s.fk_product,';
$sql .= " SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").') as stock_physique';
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$sql .= ", SUM(".$db->ifsql("s.reel IS NULL OR s.fk_entrepot <> ".$fk_entrepot, "0", "s.reel").') as stock_real_warehouse';
}

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

$sql .= ' FROM '.$db->prefix().'product as p';
$sql .= ' LEFT JOIN '.$db->prefix().'product_stock as s ON p.rowid = s.fk_product';
$list_warehouse = (empty($listofqualifiedwarehousesid) ? '0' : $listofqualifiedwarehousesid);
$sql .= ' AND s.fk_entrepot  IN ('.$db->sanitize($list_warehouse) .')';

//$sql .= ' LEFT JOIN '.$db->prefix().'entrepot AS ent ON s.fk_entrepot = ent.rowid AND ent.entity IN('.getEntity('stock').')';
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$sql .= ' LEFT JOIN '.$db->prefix().'product_warehouse_properties AS pse ON (p.rowid = pse.fk_product AND pse.fk_entrepot = '.((int) $fk_entrepot).')';
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListJoin', $parameters); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

$sql .= ' WHERE p.entity IN ('.getEntity('product').')';
if ($sall) {
	$sql .= natural_search(array('p.ref', 'p.label', 'p.description', 'p.note'), $sall);
}
// if the type is not 1, we show all products (type = 0,2,3)
if (dol_strlen($type)) {
	if ($type == 1) {
		$sql .= ' AND p.fk_product_type = 1';
	} else {
		$sql .= ' AND p.fk_product_type <> 1';
	}
}
if ($search_ref) {
	$sql .= natural_search('p.ref', $search_ref);
}
if ($search_label) {
	$sql .= natural_search('p.label', $search_label);
}
$sql .= ' AND p.fk_default_bom IS NOT NULL';
if (!empty($conf->variants->eabled) && empty($conf->global->VARIANT_ALLOW_STOCK_MOVEMENT_ON_VARIANT_PARENT)) {	// Add test to exclude products that has variants
	$sql .= ' AND p.rowid NOT IN (SELECT pac.fk_product_parent FROM '.$db->prefix().'product_attribute_combination as pac WHERE pac.entity IN ('.getEntity('product').'))';
}
if ($order_id > 0) {
	$sql .= ' AND EXISTS (SELECT od.fk_product FROM '.$db->prefix().'commande as o ';
	$sql .= ' INNER JOIN '.$db->prefix().'commandedet as od ON od.fk_commande=o.rowid ';
	$sql .= ' WHERE od.fk_product = p.rowid AND o.rowid = '.((int) $order_id).' AND o.entity IN ('.getEntity('commande').'))';
}
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

$sql .= ' GROUP BY p.rowid, p.ref, p.label, p.description, p.price';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type, p.tms';
$sql .= ', p.duration, p.tobuy';
$sql .= ', p.desiredstock';
$sql .= ', p.seuil_stock_alerte';
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$sql .= ', pse.desiredstock';
	$sql .= ', pse.seuil_stock_alerte';
}
$sql .= ', s.fk_product';

if ($usevirtualstock) {
	if (isModEnabled('commande')) {
		$sqlCommandesCli = "(SELECT ".$db->ifsql("SUM(cd1.qty) IS NULL", "0", "SUM(cd1.qty)")." as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlCommandesCli .= " FROM ".$db->prefix()."commandedet as cd1, ".$db->prefix()."commande as c1";
		$sqlCommandesCli .= " WHERE c1.rowid = cd1.fk_commande AND c1.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'commande').")";
		$sqlCommandesCli .= " AND cd1.fk_product = p.rowid";
		$sqlCommandesCli .= " AND c1.fk_statut IN (1,2))";
	} else {
		$sqlCommandesCli = '0';
	}

	if (isModEnabled("expedition")) {
		$sqlExpeditionsCli = "(SELECT ".$db->ifsql("SUM(ed2.qty) IS NULL", "0", "SUM(ed2.qty)")." as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlExpeditionsCli .= " FROM ".$db->prefix()."expedition as e2,";
		$sqlExpeditionsCli .= " ".$db->prefix()."expeditiondet as ed2,";
				$sqlExpeditionsCli .= " ".$db->prefix()."commande as c2,";
		$sqlExpeditionsCli .= " ".$db->prefix()."commandedet as cd2";
		$sqlExpeditionsCli .= " WHERE ed2.fk_expedition = e2.rowid AND cd2.rowid = ed2.fk_origin_line AND e2.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'expedition').")";
				$sqlExpeditionsCli .= " AND cd2.fk_commande = c2.rowid";
				$sqlExpeditionsCli .= " AND c2.fk_statut IN (1,2)";
		$sqlExpeditionsCli .= " AND cd2.fk_product = p.rowid";
		$sqlExpeditionsCli .= " AND e2.fk_statut IN (1,2))";
	} else {
		$sqlExpeditionsCli = '0';
	}

	if (isModEnabled("supplier_order")) {
		$sqlCommandesFourn = "(SELECT ".$db->ifsql("SUM(cd3.qty) IS NULL", "0", "SUM(cd3.qty)")." as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlCommandesFourn .= " FROM ".$db->prefix()."commande_fournisseurdet as cd3,";
		$sqlCommandesFourn .= " ".$db->prefix()."commande_fournisseur as c3";
		$sqlCommandesFourn .= " WHERE c3.rowid = cd3.fk_commande";
		$sqlCommandesFourn .= " AND c3.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'supplier_order').")";
		$sqlCommandesFourn .= " AND cd3.fk_product = p.rowid";
		$sqlCommandesFourn .= " AND c3.fk_statut IN (3,4))";

		$sqlReceptionFourn = "(SELECT ".$db->ifsql("SUM(fd4.qty) IS NULL", "0", "SUM(fd4.qty)")." as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlReceptionFourn .= " FROM ".$db->prefix()."commande_fournisseur as cf4,";
		$sqlReceptionFourn .= " ".$db->prefix()."commande_fournisseur_dispatch as fd4";
		$sqlReceptionFourn .= " WHERE fd4.fk_commande = cf4.rowid AND cf4.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'supplier_order').")";
		$sqlReceptionFourn .= " AND fd4.fk_product = p.rowid";
		$sqlReceptionFourn .= " AND cf4.fk_statut IN (3,4))";
	} else {
		$sqlCommandesFourn = '0';
		$sqlReceptionFourn = '0';
	}

	if (isModEnabled('mrp')) {
		$sqlProductionToConsume = "(SELECT GREATEST(0, ".$db->ifsql("SUM(".$db->ifsql("mp5.role = 'toconsume'", 'mp5.qty', '- mp5.qty').") IS NULL", "0", "SUM(".$db->ifsql("mp5.role = 'toconsume'", 'mp5.qty', '- mp5.qty').")").") as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlProductionToConsume .= " FROM ".$db->prefix()."mrp_mo as mm5,";
		$sqlProductionToConsume .= " ".$db->prefix()."mrp_production as mp5";
		$sqlProductionToConsume .= " WHERE mm5.rowid = mp5.fk_mo AND mm5.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'mo').")";
		$sqlProductionToConsume .= " AND mp5.fk_product = p.rowid";
		$sqlProductionToConsume .= " AND mp5.role IN ('toconsume', 'consumed')";
		$sqlProductionToConsume .= " AND mm5.status IN (1,2))";

		$sqlProductionToProduce = "(SELECT GREATEST(0, ".$db->ifsql("SUM(".$db->ifsql("mp5.role = 'toproduce'", 'mp5.qty', '- mp5.qty').") IS NULL", "0", "SUM(".$db->ifsql("mp5.role = 'toproduce'", 'mp5.qty', '- mp5.qty').")").") as qty"; // We need the ifsql because if result is 0 for product p.rowid, we must return 0 and not NULL
		$sqlProductionToProduce .= " FROM ".$db->prefix()."mrp_mo as mm5,";
		$sqlProductionToProduce .= " ".$db->prefix()."mrp_production as mp5";
		$sqlProductionToProduce .= " WHERE mm5.rowid = mp5.fk_mo AND mm5.entity IN (".getEntity(!empty($conf->global->STOCK_CALCULATE_VIRTUAL_STOCK_TRANSVERSE_MODE) ? 'stock' : 'mo').")";
		$sqlProductionToProduce .= " AND mp5.fk_product = p.rowid";
		$sqlProductionToProduce .= " AND mp5.role IN ('toproduce', 'produced')";
		$sqlProductionToProduce .= " AND mm5.status IN (1,2))";
	} else {
		$sqlProductionToConsume = '0';
		$sqlProductionToProduce = '0';
	}

	$sql .= ' HAVING (';
	$sql .= " (".$sqldesiredtock." >= 0 AND (".$sqldesiredtock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").')';
	$sql .= " - (".$sqlCommandesCli." - ".$sqlExpeditionsCli.") + (".$sqlCommandesFourn." - ".$sqlReceptionFourn.") + (".$sqlProductionToProduce." - ".$sqlProductionToConsume.")))";
	$sql .= ' OR';
	if ($includeproductswithoutdesiredqty == 'on') {
		$sql .= " ((".$sqlalertstock." >= 0 OR ".$sqlalertstock." IS NULL) AND (".$db->ifsql($sqlalertstock." IS NULL", "0", $sqlalertstock)." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").")";
	} else {
		$sql .= " (".$sqlalertstock." >= 0 AND (".$sqlalertstock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").')';
	}
	$sql .= " - (".$sqlCommandesCli." - ".$sqlExpeditionsCli.") + (".$sqlCommandesFourn." - ".$sqlReceptionFourn.") + (".$sqlProductionToProduce." - ".$sqlProductionToConsume.")))";
	$sql .= ")";

	if ($salert == 'on') {	// Option to see when stock is lower than alert
		$sql .= ' AND (';
		if ($includeproductswithoutdesiredqty == 'on') {
			$sql .= "(".$sqlalertstock." >= 0 OR ".$sqlalertstock." IS NULL) AND (".$db->ifsql($sqlalertstock." IS NULL", "0", $sqlalertstock)." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").")";
		} else {
			$sql .= $sqlalertstock." >= 0 AND (".$sqlalertstock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").")";
		}
		$sql .= " - (".$sqlCommandesCli." - ".$sqlExpeditionsCli.") + (".$sqlCommandesFourn." - ".$sqlReceptionFourn.")  + (".$sqlProductionToProduce." - ".$sqlProductionToConsume."))";
		$sql .= ")";
		$alertchecked = 'checked';
	}
} else {
	$sql .= ' HAVING (';
	$sql .= "(".$sqldesiredtock." >= 0 AND (".$sqldesiredtock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").")))";
	$sql .= ' OR';
	if ($includeproductswithoutdesiredqty == 'on') {
		$sql .= " ((".$sqlalertstock." >= 0 OR ".$sqlalertstock." IS NULL) AND (".$db->ifsql($sqlalertstock." IS NULL", "0", $sqlalertstock)." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").')))';
	} else {
		$sql .= " (".$sqlalertstock." >= 0 AND (".$sqlalertstock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").')))';
	}
	$sql .= ')';

	if ($salert == 'on') {	// Option to see when stock is lower than alert
		$sql .= " AND (";
		if ($includeproductswithoutdesiredqty == 'on') {
			$sql .= " (".$sqlalertstock." >= 0 OR ".$sqlalertstock." IS NULL) AND (".$db->ifsql($sqlalertstock." IS NULL", "0", $sqlalertstock)." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel")."))";
		} else {
			$sql .= " ".$sqlalertstock." >= 0 AND (".$sqlalertstock." > SUM(".$db->ifsql("s.reel IS NULL", "0", "s.reel").'))';
		}
		$sql .= ')';
		$alertchecked = 'checked';
	}
}

$includeproductswithoutdesiredqtychecked = '';
if ($includeproductswithoutdesiredqty == 'on') {
	$includeproductswithoutdesiredqtychecked = 'checked';
}

$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);


//print $sql;
$resql = $db->query($sql);
if (empty($resql)) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);
$i = 0;



llxHeader('', $title);

$head = array();

$head[0][0] = dol_buildpath('/gpaodoli/replenish_mrp.php',2).(!empty($order_id)?'?order_id='.$order_id:'');
$head[0][1] = $title;
$head[0][2] = 'gpaoDoliMenuProd';


print load_fiche_titre($langs->trans('gpaoDoliMenuProd'), '', 'mrp');

print dol_get_fiche_head($head, 'gpaoDoliMenuProd', '', -1, '');


print '<span class="opacitymedium">'.$langs->trans("gpaoDoliMenuProdDesc").'</span>'."\n";

//$link = '<a title=' .$langs->trans("MenuNewWarehouse"). ' href="'.DOL_URL_ROOT.'/product/stock/card.php?action=create">'.$langs->trans("MenuNewWarehouse").'</a>';

if (empty($fk_entrepot) && !empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE)) {
	print '<span class="opacitymedium">'.$langs->trans("ReplenishmentStatusDescPerWarehouse").'</span>'."\n";
}
print '<br><br>';
if ($usevirtualstock == 1) {
	print $langs->trans("CurentSelectionMode").': ';
	print '<span class="a-mesure">'.$langs->trans("UseVirtualStock").'</span>';
	print ' <a class="a-mesure-disabled" href="'.$_SERVER["PHP_SELF"].'?mode=physical'.($fk_entrepot > 0 ? '&fk_entrepot='.$fk_entrepot : '').'">'.$langs->trans("UsePhysicalStock").'</a>';
	print '<br>';
}
if ($usevirtualstock == 0) {
	print $langs->trans("CurentSelectionMode").': ';
	print '<a class="a-mesure-disabled" href="'.$_SERVER["PHP_SELF"].'?mode=virtual'.($fk_entrepot > 0 ? '&fk_entrepot='.$fk_entrepot : '').'">'.$langs->trans("UseVirtualStock").'</a>';
	print ' <span class="a-mesure">'.$langs->trans("UsePhysicalStock").'</span>';
	print '<br>';
}
print '<br>'."\n";

print '<form name="formFilterWarehouse" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="filter">';
print '<input type="hidden" name="search_ref" value="'.$search_ref.'">';
print '<input type="hidden" name="search_label" value="'.$search_label.'">';
print '<input type="hidden" name="salert" value="'.$salert.'">';
print '<input type="hidden" name="includeproductswithoutdesiredqty" value="'.$includeproductswithoutdesiredqty.'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';
if ($limit > 0 && $limit != $conf->liste_limit) {
	print '<input type="hidden" name="limit" value="'.$limit.'">';
}
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE)) {
	print '<div class="inline-block valignmiddle" style="padding-right: 20px;">';
	print $langs->trans('Warehouse').' '.$formproduct->selectWarehouses($fk_entrepot, 'fk_entrepot', '', 1);
	print '</div>';
}

if ($search_ref || $search_label || $sall || $salert || GETPOST('search', 'alpha')) {
	$filters = '&search_ref='.urlencode($search_ref).'&search_label='.urlencode($search_label);
	$filters .= '&sall='.urlencode($sall);
	$filters .= '&salert='.urlencode($salert);
	$filters .= '&mode='.urlencode($mode);
	$filters .= '&order_id='.urlencode($order_id);
	if ($fk_entrepot > 0) {
		$filters .= '&fk_entrepot='.urlencode($fk_entrepot);
	}
} else {
	$filters = '&search_ref='.urlencode($search_ref).'&search_label='.urlencode($search_label);
	$filters .= '&order_id='.urlencode($order_id);
	$filters .= (isset($type) ? '&type='.urlencode($type) : '');
	$filters .= '&='.urlencode($salert);
	$filters .= '&mode='.urlencode($mode);
	if ($fk_entrepot > 0) {
		$filters .= '&fk_entrepot='.urlencode($fk_entrepot);
	}
}

$param = (isset($type) ? '&type='.urlencode($type) : '');
$param .= '&search_label='.urlencode($search_label).'&includeproductswithoutdesiredqty='.urlencode($includeproductswithoutdesiredqty).'&salert='.urlencode($salert).'&order_id='.urlencode($order_id);
$param .= '&search_ref='.urlencode($search_ref);
$param .= '&mode='.urlencode($mode);
$param .= '&fk_entrepot='.urlencode($fk_entrepot);
$param .= '&order_id='.urlencode($order_id);
if (!empty($includeproductswithoutdesiredqty)) $param .= '&includeproductswithoutdesiredqty='.urlencode($includeproductswithoutdesiredqty);
if (!empty($salert)) $param .= '&salert='.urlencode($salert);

$stocklabel = $langs->trans('Stock');
$stocklabelbis = $langs->trans('Stock');
$stocktooltip = '';
if ($usevirtualstock == 1) {
	$stocklabel = $langs->trans('VirtualStock');
	$stocktooltip = $langs->trans("VirtualStockDesc");
}
if ($usevirtualstock == 0) {
	$stocklabel = $langs->trans('PhysicalStock');
}
if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
	$stocklabelbis = $stocklabel.' (Selected warehouse)';
	$stocklabel .= ' ('.$langs->trans("AllWarehouses").')';
}
$texte = $langs->trans('Replenishment');

print '<br>';


print '<div class="inline-block valignmiddle" style="padding-right: 20px;">';
print $langs->trans('gpaoDoliOrderNotClosed').' ';
$sqlOrder = 'SELECT c.rowid, c.ref, s.nom, c.date_commande ';
$sqlOrder .= 'FROM '.$db->prefix().'commande as c ';
$sqlOrder .= ' INNER JOIN '.$db->prefix().'societe as s ON s.rowid=c.fk_soc';
$sqlOrder .= ' WHERE c.entity IN ('.getEntity('commande').') AND c.fk_statut NOT IN ('.Commande::STATUS_CLOSED.','.Commande::STATUS_DRAFT.')';
$sqlOrder .= $db->order('c.date_commande', 'DESC');
$resqlOrder = $db->query($sqlOrder);
$orderFilterable=[];
if (!$resqlOrder) {
	setEventMessage($db->lasterror,'errors');
} else {
	$numOrder = $db->num_rows($resqlOrder);
	if ($numOrder>0) {
		while ($objOrder = $db->fetch_object($resqlOrder)) {
			$orderFilterable[$objOrder->rowid] = $objOrder->ref . ' - '. $objOrder->nom . '('.dol_print_date($objOrder->date_commande).')';
		}
	}

 	print $form->selectarray('order_id', $orderFilterable, $order_id,  1);

}
print '</div>';

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	print $hookmanager->resPrint;
}

print '<div class="inline-block valignmiddle">';
print '<input type="submit" class="button smallpaddingimp" name="valid" value="'.$langs->trans('ToFilter').'">';
print '</div>';

if (!empty($conf->global->REPLENISH_ALLOW_VARIABLESIZELIST)) {
	print_barre_liste(
		$texte,
		$page,
		dol_buildpath('/gpaodoli/replenish_mrp.php',2),
		$filters,
		$sortfield,
		$sortorder,
		'',
		$num,
		$nbtotalofrecords,
		'',
		0,
		'',
		'',
		$limit
	);
} else {
	print_barre_liste(
		$texte,
		$page,
		dol_buildpath('/gpaodoli/replenish_mrp.php',2),
		$filters,
		$sortfield,
		$sortorder,
		'',
		$num,
		$nbtotalofrecords,
		''
	);
}

print '<div class="div-table-responsive-no-min">';
print '<table class="liste centpercent">';

// Fields title search
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">&nbsp;</td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_ref" size="8" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre"><input class="flat" type="text" name="search_label" size="8" value="'.dol_escape_htmltag($search_label).'"></td>';
print '<td class="liste_titre right">'.$form->textwithpicto($langs->trans('IncludeEmptyDesiredStock'), $langs->trans('IncludeProductWithUndefinedAlerts')).'&nbsp;<input type="checkbox" id="includeproductswithoutdesiredqty" name="includeproductswithoutdesiredqty" '.(!empty($includeproductswithoutdesiredqtychecked) ? $includeproductswithoutdesiredqtychecked : '').'></td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre right">'.$langs->trans('AlertOnly').'&nbsp;<input type="checkbox" id="salert" name="salert" '.(!empty($alertchecked) ? $alertchecked : '').'></td>';
//if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
//	print '<td class="liste_titre">&nbsp;</td>';
//}
//print '<td class="liste_titre right">';
//if (!empty($conf->global->STOCK_REPLENISH_ADD_CHECKBOX_INCLUDE_DRAFT_ORDER)) {
//	print $langs->trans('IncludeAlsoDraftOrders').'&nbsp;<input type="checkbox" id="draftorder" name="draftorder" '.(!empty($draftchecked) ? $draftchecked : '').'>';
//}
//print '</td>';
print '<td class="liste_titre">&nbsp;</td>';
// Fields from hook
$parameters = array('param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '<td class="liste_titre maxwidthsearch right">';
$searchpicto = $form->showFilterAndCheckAddButtons(0);
print $searchpicto;
print '</td>';
print '</tr>';

// Lines of title
print '<tr class="liste_titre">';
print_liste_field_titre('<input type="checkbox" onClick="toggle(this)" />', $_SERVER["PHP_SELF"], '');
print_liste_field_titre('ProductRef', $_SERVER["PHP_SELF"], 'p.ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Label', $_SERVER["PHP_SELF"], 'p.label', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DesiredStock', $_SERVER["PHP_SELF"], 'p.desiredstock', $param, '', '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('StockLimitShort', $_SERVER["PHP_SELF"], 'p.seuil_stock_alerte', $param, '', '', $sortfield, $sortorder, 'right ');
//print_liste_field_titre($stocklabel, $_SERVER["PHP_SELF"], 'stock_physique', $param, '', '', $sortfield, $sortorder, 'right ', $stocktooltip);
//if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
//	print_liste_field_titre($stocklabelbis, $_SERVER["PHP_SELF"], 'stock_real_warehouse', $param, '', '', $sortfield, $sortorder, 'right ');
//}
print_liste_field_titre('Ordered', $_SERVER["PHP_SELF"], '', $param, '', '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('StockToProduce', $_SERVER["PHP_SELF"], '', $param, '', '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('BOM', $_SERVER["PHP_SELF"], '', $param, '', '', $sortfield, $sortorder, 'right ');

// Hook fields
$parameters = array('param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print "</tr>\n";

while ($i < ($limit ? min($num, $limit) : $num)) {
	$objp = $db->fetch_object($resql);

	if (!empty($conf->global->STOCK_SUPPORTS_SERVICES) || $objp->fk_product_type == 0) {
		$result = $prod->fetch($objp->rowid);
		if ($result < 0) {
			dol_print_error($db);
			exit;
		}

		$prod->load_stock('warehouseopen, warehouseinternal'.(!$usevirtualstock?', novirtual':''), $draftchecked);

		// Multilangs
		if (getDolGlobalInt('MAIN_MULTILANGS')) {
			$sql = 'SELECT label,description';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'product_lang';
			$sql .= ' WHERE fk_product = '.((int) $objp->rowid);
			$sql .= " AND lang = '".$db->escape($langs->getDefaultLang())."'";
			$sql .= ' LIMIT 1';

			$resqlm = $db->query($sql);
			if ($resqlm) {
				$objtp = $db->fetch_object($resqlm);
				if (!empty($objtp->description)) {
					$objp->description = $objtp->description;
				}
				if (!empty($objtp->label)) {
					$objp->label = $objtp->label;
				}
			}
		}

		$stockwarehouse = 0;
		if ($usevirtualstock) {
			// If option to increase/decrease is not on an object validation, virtual stock may differs from physical stock.
			$stock = $prod->stock_theorique;
			//TODO $stockwarehouse = $prod->stock_warehouse[$fk_entrepot]->;
		} else {
			$stock = $prod->stock_reel;
			$stockwarehouse = $prod->stock_warehouse[$fk_entrepot]->real;
		}

		// Force call prod->load_stats_xxx to choose status to count (otherwise it is loaded by load_stock function)
		if (isset($draftchecked)) {
			$result = $prod->load_stats_commande_fournisseur(0, '0,1,2,3,4');
		} elseif (!$usevirtualstock) {
			$result = $prod->load_stats_commande_fournisseur(0, '1,2,3,4');
		}

		if (!$usevirtualstock) {
			$result = $prod->load_stats_reception(0, '4');
		}

		//print $prod->stats_commande_fournisseur['qty'].'<br>'."\n";
		//print $prod->stats_reception['qty'];
		$ordered = $prod->stats_commande_fournisseur['qty'] - $prod->stats_reception['qty'];

		$desiredstock = $objp->desiredstock;
		$alertstock = $objp->seuil_stock_alerte;
		$desiredstockwarehouse = (!empty($objp->desiredstockpse) ? $objp->desiredstockpse : 0);
		$alertstockwarehouse = (!empty($objp->seuil_stock_alertepse) ? $objp->seuil_stock_alertepse : 0);

		$warning = '';
		if ($alertstock && ($stock < $alertstock)) {
			$warning = img_warning($langs->trans('StockTooLow')).' ';
		}
		$warningwarehouse = '';
		if ($alertstockwarehouse && ($stockwarehouse < $alertstockwarehouse)) {
			$warningwarehouse = img_warning($langs->trans('StockTooLow')).' ';
		}

		//depending on conf, use either physical stock or
		//virtual stock to compute the stock to buy value

		if (empty($usevirtualstock)) {
			$stocktobuy = max(max($desiredstock, $alertstock) - $stock - $ordered, 0);
		} else {
			$stocktobuy = max(max($desiredstock, $alertstock) - $stock, 0); //ordered is already in $stock in virtual mode
		}
		if (empty($usevirtualstock)) {
			$stocktobuywarehouse = max(max($desiredstockwarehouse, $alertstockwarehouse) - $stockwarehouse - $ordered, 0);
		} else {
			$stocktobuywarehouse = max(max($desiredstockwarehouse, $alertstockwarehouse) - $stockwarehouse, 0); //ordered is already in $stock in virtual mode
		}

		$picto = '';
		if ($ordered > 0) {
			$stockforcompare = ($usevirtualstock ? $stock : $stock + $ordered);
			/*if ($stockforcompare >= $desiredstock)
			{
				$picto = img_picto('', 'help');
			} else {
				$picto = img_picto('', 'help');
			}*/
		} else {
			$picto = img_picto($langs->trans("NoPendingReceptionOnSupplierOrder"), 'help');
		}

		print '<tr class="oddeven">';

		// Select field
		print '<td><input type="checkbox" class="check" name="choose'.$i.'"></td>';

		print '<td class="nowrap">'.$prod->getNomUrl(1, 'stock').'</td>';

		print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($objp->label).'">';
		print dol_escape_htmltag($objp->label);
		print '<input type="hidden" name="desc'.$i.'" value="'.dol_escape_htmltag($objp->description).'">'; // TODO Remove this and make a fetch to get description when creating order instead of a GETPOST
		print '</td>';

		if (isModEnabled("service") && $type == 1) {
			$regs = array();
			if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
				$duration = $regs[1].' '.$langs->trans('DurationYear');
			} elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
				$duration = $regs[1].' '.$langs->trans('DurationMonth');
			} elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
				$duration = $regs[1].' '.$langs->trans('DurationDay');
			} else {
				$duration = $objp->duration;
			}
			print '<td class="center">'.$duration.'</td>';
		}

		// Desired stock
		print '<td class="right">'.((!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) > 0 ? $desiredstockwarehouse : $desiredstock).'</td>';

		// Limit stock for alert
		print '<td class="right">'.((!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) > 0 ? $alertstockwarehouse : $alertstock).'</td>';

		// Current stock (all warehouses)
		print '<td class="right">'.$warning.$stock;
		print '<!-- stock returned by main sql is '.$objp->stock_physique.' -->';
		print '</td>';

		// Current stock (warehouse selected only)
		if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
			print '<td class="right">'.$warningwarehouse.$stockwarehouse.'</td>';
		}

		// Already ordered
		print '<td class="right"><a href="replenishorders.php?search_product='.$prod->id.'">'.$ordered.'</a> '.$picto.'</td>';

		// To order
		print '<td class="right"><input type="text" size="4" name="tobuy'.$i.'" value="'.((!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) > 0 ? $stocktobuywarehouse : $stocktobuy).'"></td>';

		// Supplier
		print '<td class="right">';
		//print $form->select_product_fourn_price($prod->id, 'fourn'.$i, $fk_supplier);
		print '</td>';

		// Fields from hook
		$parameters = array('objp'=>$objp);
		$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters); // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		print '</tr>';
	}
	$i++;
}


if ($num == 0) {
	$colspan = 9;
	if (!empty($conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE) && $fk_entrepot > 0) {
		$colspan++;
	}
	print '<tr><td colspan="'.$colspan.'">';
	print '<span class="opacitymedium">';
	print $langs->trans("None");
	print '</span>';
	print '</td></tr>';
}

$parameters = array('sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>';
print '</div>';

$db->free($resql);

print dol_get_fiche_end();


$value = $langs->trans("CreateOrders");
print '<div class="center"><input type="submit" class="button" name="valid" value="'.$value.'"></div>';





print '</form>';

// End of page
llxFooter();
$db->close();
