<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *	\file       htdocs/comm/index.php
 *	\ingroup    commercial
 *	\brief      Page acceuil de la zone commercial cliente
 *	\version    $Id$
 */

require("./pre.inc.php");
require_once(DOL_DOCUMENT_ROOT."/html.formfile.class.php");
require_once(DOL_DOCUMENT_ROOT."/client.class.php");
if ($conf->contrat->enabled) require_once(DOL_DOCUMENT_ROOT."/contrat/contrat.class.php");
if ($conf->propal->enabled)  require_once(DOL_DOCUMENT_ROOT."/propal.class.php");
require_once(DOL_DOCUMENT_ROOT."/actioncomm.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/agenda.lib.php");

if (!$user->rights->societe->lire)
accessforbidden();

$langs->load("commercial");

// Securite acces client
$socid='';
if ($_GET["socid"]) { $socid=$_GET["socid"]; }
if ($user->societe_id > 0)
{
	$action = '';
	$socid = $user->societe_id;
}

$max=5;

/*
 * Actions
 */

if (isset($_GET["action"]) && $_GET["action"] == 'add_bookmark')
{
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."bookmark WHERE fk_soc = ".$_GET["socid"]." AND fk_user=".$user->id;
	if (! $db->query($sql) )
	{
		dol_print_error($db);
	}
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."bookmark (fk_soc, dateb, fk_user) VALUES (".$_GET["socid"].", ".$db->idate(mktime()).",".$user->id.");";
	if (! $db->query($sql) )
	{
		dol_print_error($db);
	}
}

if (isset($_GET["action"]) && $_GET["action"] == 'del_bookmark')
{
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."bookmark WHERE rowid=".$_GET["bid"];
	$result = $db->query($sql);
}


/*
 * View
 */

$now=gmmktime();

$html = new Form($db);
$formfile = new FormFile($db);
$companystatic=new Societe($db);
if ($conf->propal->enabled) $propalstatic=new Propal($db);

llxHeader();

print_fiche_titre($langs->trans("CustomerArea"));

print '<table border="0" width="100%" class="notopnoleftnoright">';

print '<tr>';
if (($conf->propal->enabled && $user->rights->propale->lire) ||
    ($conf->contrat->enabled && $user->rights->contrat->lire) ||
    ($conf->commande->enabled && $user->rights->commande->lire))
{
	print '<td valign="top" width="30%" class="notopnoleft">';
}

// Recherche Propal
if ($conf->propal->enabled && $user->rights->propale->lire)
{
	$var=false;
	print '<form method="post" action="'.DOL_URL_ROOT.'/comm/propal.php">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("SearchAProposal").'</td></tr>';
	print '<tr '.$bc[$var].'>';
	print '<td nowrap>'.$langs->trans("Ref").':</td><td><input type="text" class="flat" name="sf_ref" size="18"></td>';
	print '<td rowspan="2"><input type="submit" value="'.$langs->trans("Search").'" class="button"></td></tr>';
	print '<tr '.$bc[$var].'><td nowrap>'.$langs->trans("Other").':</td><td><input type="text" class="flat" name="sall" size="18"></td>';
	print '</tr>';
	print "</table></form>\n";
	print "<br />\n";
}

/*
 * Recherche Contrat
 */
if ($conf->contrat->enabled && $user->rights->contrat->lire)
{
	$var=false;
	print '<form method="post" action="'.DOL_URL_ROOT.'/contrat/liste.php">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("SearchAContract").'</td></tr>';
	print '<tr '.$bc[$var].'>';
	print '<td nowrap>'.$langs->trans("Ref").':</td><td><input type="text" class="flat" name="search_contract" size="18"></td>';
	print '<td rowspan="2"><input type="submit" value="'.$langs->trans("Search").'" class="button"></td></tr>';
	print '<tr '.$bc[$var].'><td nowrap>'.$langs->trans("Other").':</td><td><input type="text" class="flat" name="sall" size="18"></td>';
	print '</tr>';
	print "</table></form>\n";
	print "<br>";
}

/*
 * Draft proposals
 */
if ($conf->propal->enabled && $user->rights->propale->lire)
{
	$sql = "SELECT p.rowid, p.ref, p.total_ht, s.rowid as socid, s.nom, s.client";
	$sql.= " FROM ".MAIN_DB_PREFIX."propal as p";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE p.fk_statut = 0";
	$sql.= " AND p.fk_soc = s.rowid";
	$sql.= " AND p.entity = ".$conf->entity;
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid)	$sql.= " AND s.rowid = ".$socid;

	$resql=$db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);
		if ($num > 0)
		{
			print '<table class="noborder" width="100%">';
			print "<tr class=\"liste_titre\">";
			print "<td colspan=\"3\">".$langs->trans("ProposalsDraft")."</td></tr>";

			$i = 0;
			$var=true;
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				$var=!$var;
				print '<tr '.$bc[$var].'><td  nowrap="nowrap">';
				$propalstatic->id=$obj->rowid;
				$propalstatic->ref=$obj->ref;
				print $propalstatic->getNomUrl(1);
				print '</td>';
				print '<td nowrap="nowrap">';
				$companystatic->id=$obj->socid;
				$companystatic->nom=$obj->nom;
				$companystatic->client=$obj->client;
				print $companystatic->getNomUrl(1,'customer',16);
				print '</td>';
				print '<td align="right" nowrap="nowrap">'.price($obj->total_ht).'</td></tr>';
				$i++;
				$total += $obj->price;
			}
			if ($total>0)
			{
				$var=!$var;
				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" align="right">'.price($total)."</td></tr>";
			}
			print "</table><br>";
		}
		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}


/*
 * Draft orders
 */
if ($conf->commande->enabled && $user->rights->commande->lire)
{
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.total_ttc, s.rowid as socid, s.nom, s.client";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande as c";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity = ".$conf->entity;
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid)	$sql.= " AND c.fk_soc = ".$socid;

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);
		if ($num)
		{
			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
			print '<td colspan="3">'.$langs->trans("DraftOrders").'</td></tr>';

			$i = 0;
			$var = true;
			while ($i < $num)
			{
				$var=!$var;
				$obj = $db->fetch_object($resql);
				print '<tr '.$bc[$var].'><td nowrap="nowrap"><a href="../commande/fiche.php?id='.$obj->rowid.'">'.img_object($langs->trans("ShowOrder"),"order").' '.$obj->ref.'</a></td>';
				print '<td nowrap="nowrap">';
				$companystatic->id=$obj->socid;
				$companystatic->nom=$obj->nom;
				$companystatic->client=$obj->client;
				print $companystatic->getNomUrl(1,'customer',16);
				print '</td>';
				print '<td align="right" nowrap="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{
				$var=!$var;
				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" align="right">'.price($total)."</td></tr>";
			}
			print "</table><br>";
		}
	}
}

if (($conf->propal->enabled && $user->rights->propale->lire) ||
    ($conf->contrat->enabled && $user->rights->contrat->lire) ||
    ($conf->commande->enabled && $user->rights->commande->lire))
{
	print '</td>';
	print '<td valign="top" width="70%" class="notopnoleftnoright">';
}
else
{
	print '<td valign="top" width="100%" class="notopnoleftnoright">';
}



$NBMAX=3;
$max=3;


/*
 * Last modified proposals
 */

if ($conf->propal->enabled && $user->rights->propale->lire)
{
	$sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.total_ht, p.ref, p.fk_statut, ".$db->pdate("p.datep")." as dp";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql.= ", ".MAIN_DB_PREFIX."propal as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE p.fk_soc = s.rowid";
	$sql.= " AND p.entity = ".$conf->entity;
	//$sql.= " AND p.fk_statut > 1";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid)	$sql.= " AND s.rowid = ".$socid;
	$sql.= " ORDER BY p.datec DESC";
	$sql.= $db->plimit($NBMAX, 0);

	if ( $db->query($sql) )
	{
		$num = $db->num_rows();

		$i = 0;
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre"><td colspan="6">'.$langs->trans("LastModifiedProposals",$NBMAX).'</td></tr>';
		$var=False;
		while ($i < $num)
		{
			$objp = $db->fetch_object();
			print "<tr $bc[$var]>";

			// Ref
			print '<td nowrap="nowrap" width="140">';

			$propalstatic->id=$objp->propalid;
			$propalstatic->ref=$objp->ref;

			print '<table class="nobordernopadding"><tr class="nocellnopadd">';
			print '<td class="nobordernopadding" nowrap="nowrap">';
			print $propalstatic->getNomUrl(1);
			print '</td>';
			print '<td width="18" class="nobordernopadding" nowrap="nowrap">';
			if (($objp->fk_statut <= 1) && $objp->dp < ($now - $conf->propal->cloture->warning_delay)) print img_warning($langs->trans("Late"));
			print '</td>';
			print '<td width="16" align="center" class="nobordernopadding">';
			$filename=dol_sanitizeFileName($objp->ref);
			$filedir=$conf->propale->dir_output . '/' . dol_sanitizeFileName($objp->ref);
			$urlsource=$_SERVER['PHP_SELF'].'?propalid='.$objp->propalid;
			$formfile->show_documents('propal',$filename,$filedir,$urlsource,'','','','','',1);
			print '</td></tr></table>';

			print '</td>';

			print '<td align="left"><a href="fiche.php?socid='.$objp->rowid.'">'.img_object($langs->trans("ShowCompany"),"company").' '.dol_trunc($objp->nom,44).'</a></td>';
			print "<td align=\"right\">";
			print dol_print_date($objp->dp,'day')."</td>\n";
			print "<td align=\"right\">".price($objp->total_ht)."</td>\n";
			print "<td align=\"center\" width=\"14\">".$propalstatic->LibStatut($objp->fk_statut,3)."</td>\n";
			print "</tr>\n";
			$i++;
			$var=!$var;

		}

		print "</table><br>";
		$db->free();
	}
}

/*
 * Last modified customers or prospects
 */
if ($conf->societe->enabled && $user->rights->societe->lire)
{
	$langs->load("boxes");

	$sql = "SELECT s.rowid,s.nom,s.client,s.tms";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE s.client IN (1, 2, 3)";
	$sql.= " AND s.entity = ".$conf->entity;
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid)	$sql.= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql)
	{
		$var=false;
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.$langs->trans("BoxTitleLastCustomersOrProspects",$max).'</td>';
		print '<td align="right">'.$langs->trans("DateModificationShort").'</td>';
		print '</tr>';
		if ($num)
		{
			$company=new Societe($db);
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);
				$company->id=$objp->rowid;
				$company->nom=$objp->nom;
				$company->client=$objp->client;
				print '<tr '.$bc[$var].'>';
				print '<td nowrap="nowrap">'.$company->getNomUrl(1,'customer',48).'</td>';
				print '<td align="right" nowrap>';
				if ($objp->client == 2 || $objp->client == 3) print $langs->trans("Prospect");
				if ($objp->client == 3) print ' / ';
				if ($objp->client == 1 || $objp->client == 3) print $langs->trans("Customer");
				print "</td>";
				print '<td align="right" nowrap>'.dol_print_date($db->jdate($objp->tms),'day')."</td>";
				print '</tr>';
				$i++;
				$var=!$var;

			}
			print "</table><br>";

			$db->free($resql);
		}
		else
		{
			print '<tr '.$bc[$var].'><td colspan="3">'.$langs->trans("None").'</td></tr>';
		}
	}
}

// Last suppliers
if ($conf->fournisseur->enabled && $user->rights->societe->lire)
{
	$langs->load("boxes");

	$sql = "SELECT s.nom, s.rowid, ".$db->pdate("s.datec")." as dc";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE s.fournisseur = 1";
	$sql.= " AND s.entity = ".$conf->entity;
	if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid)	$sql.= " AND s.rowid = ".$socid;
	$sql.= " ORDER BY s.datec DESC";
	$sql.= $db->plimit($max, 0);

	$result = $db->query($sql);
	if ($result)
	{
		$var=false;
		$num = $db->num_rows($result);
		$i = 0;

		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre"><td>'.$langs->trans("BoxTitleLastSuppliers",min($max,$num)).'</td>';
		print '<td align="right">'.$langs->trans("DateModificationShort").'</td>';
		print '</tr>';
		if ($num)
		{
			$company=new Societe($db);
			while ($i < $num && $i < $max)
			{
				$objp = $db->fetch_object($result);
				$company->id=$objp->rowid;
				$company->nom=$objp->nom;
				print '<tr '.$bc[$var].'>';
				print '<td nowrap="nowrap">'.$company->getNomUrl(1,'supplier',48).'</td>';
				print '<td align="right">'.dol_print_date($objp->dc,'day').'</td>';
				print '</tr>';
				$var=!$var;
				$i++;
			}

		}
		else
		{
			print '<tr '.$bc[$var].'><td colspan="2">'.$langs->trans("None").'</td></tr>';
		}
		print '</table><br>';
	}
}


/*
 * Last actions
 */
if ($user->rights->agenda->myactions->read)
{
	show_array_last_actions_done($max);
}


/*
 * Actions to do
 */
if ($user->rights->agenda->myactions->read)
{
	show_array_actions_to_do(10);
}


/*
 * Derniers contrats
 *
 */
if ($conf->contrat->enabled && $user->rights->contrat->lire && 0) // \todo A REFAIRE DEPUIS NOUVEAU CONTRAT
{
	$langs->load("contracts");

	$sql = "SELECT s.nom, s.rowid, c.statut, c.rowid as contratid, p.ref, c.mise_en_service as datemes, c.fin_validite as datefin, c.date_cloture as dateclo";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql.= ", ".MAIN_DB_PREFIX."contrat as c";
	$sql.= ", ".MAIN_DB_PREFIX."product as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND s.entity = ".$conf->entity;
	//$sql.= " AND c.entity = ".$conf->entity;
	$sql.= " AND c.fk_product = p.rowid";
	if (!$user->rights->societe->client->voir && !$socid)	$sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid) $sql.= " AND s.rowid = ".$socid;
	$sql.= " ORDER BY c.tms DESC";
	$sql.= $db->plimit(5, 0);

	if ( $db->query($sql) )
	{
		$num = $db->num_rows();

		if ($num > 0)
		{
			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("LastContracts",5).'</td></tr>';
			$i = 0;

			$staticcontrat=new Contrat($db);

			$var=false;
			while ($i < $num)
			{
				$obj = $db->fetch_object();
				print "<tr $bc[$var]><td><a href=\"../contrat/fiche.php?id=".$obj->contratid."\">".img_object($langs->trans("ShowContract","contract"))." ".$obj->ref."</a></td>";
				print "<td><a href=\"fiche.php?socid=".$obj->rowid."\">".img_object($langs->trans("ShowCompany","company"))." ".$obj->nom."</a></td>\n";
				print "<td align=\"right\">".$staticcontrat->LibStatut($obj->statut,3)."</td></tr>\n";
				$var=!$var;
				$i++;
			}
			print "</table><br>";
		}
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * Propales ouvertes
 *
 */
if ($conf->propal->enabled && $user->rights->propale->lire)
{
	$langs->load("propal");

	$sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.total as total_ttc, p.total_ht, p.ref, p.fk_statut, ".$db->pdate("p.datep")." as dp";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql.= ", ".MAIN_DB_PREFIX."propal as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE p.fk_soc = s.rowid";
	$sql.= " AND p.entity = ".$conf->entity;
	$sql.= " AND p.fk_statut = 1";
	if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid) $sql.= " AND s.rowid = ".$socid;
	$sql.= " ORDER BY p.rowid DESC";

	$result=$db->query($sql);
	if ($result)
	{
		$total = 0;
		$num = $db->num_rows($result);
		$i = 0;
		if ($num > 0)
		{
			$var=true;

			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre"><td colspan="5">'.$langs->trans("ProposalsOpened").'</td></tr>';
			while ($i < $num)
			{
				$obj = $db->fetch_object($result);
				$var=!$var;
				print "<tr $bc[$var]>";

				// Ref
				print '<td nowrap="nowrap" width="140">';

				$propalstatic->id=$obj->propalid;
				$propalstatic->ref=$obj->ref;

				print '<table class="nobordernopadding"><tr class="nocellnopadd">';
				print '<td class="nobordernopadding" nowrap="nowrap">';
				print $propalstatic->getNomUrl(1);
				print '</td>';
				print '<td width="18" class="nobordernopadding" nowrap="nowrap">';
				if ($obj->dp < ($now - $conf->propal->cloture->warning_delay)) print img_warning($langs->trans("Late"));
				print '</td>';
				print '<td width="16" align="center" class="nobordernopadding">';
				$filename=dol_sanitizeFileName($obj->ref);
				$filedir=$conf->propale->dir_output . '/' . dol_sanitizeFileName($obj->ref);
				$urlsource=$_SERVER['PHP_SELF'].'?propalid='.$obj->propalid;
				$formfile->show_documents('propal',$filename,$filedir,$urlsource,'','','','','',1);
				print '</td></tr></table>';

				print "</td>";

				print "<td align=\"left\"><a href=\"fiche.php?socid=".$obj->rowid."\">".img_object($langs->trans("ShowCompany"),"company")." ".dol_trunc($obj->nom,44)."</a></td>\n";
				print "<td align=\"right\">";
				print dol_print_date($obj->dp,'day')."</td>\n";
				print "<td align=\"right\">".price($obj->total_ttc)."</td>";
				print "<td align=\"center\" width=\"14\">".$propalstatic->LibStatut($obj->fk_statut,3)."</td>\n";
				print "</tr>\n";
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0) {
				print '<tr class="liste_total"><td colspan="3">'.$langs->trans("Total")."</td><td align=\"right\">".price($total)."</td><td>&nbsp;</td></tr>";
			}
			print "</table><br>";
		}
	}
	else
	{
		dol_print_error($db);
	}
}


print '</td></tr>';
print '</table>';

$db->close();


llxFooter('$Date$ - $Revision$');
?>
