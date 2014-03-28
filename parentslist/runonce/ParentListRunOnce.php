<?php

/**
 * parentslist
 * 
 * Copyright (C) 2014 Scyfel (Christian Barkowsky & Jan Theofel)
 * 
 * @package parentslist
 * @author  Christian Barkowsky <http://christianbarkowsky.de>
 * @author  Jan Theofel <http://theofel.com>  
 * @author  Sebastian Leitz <http://www.etes.de>
 * @link    http://scyfel.de
 * @license LGPL
 */


namespace Contao;


class ParentListRunOnce
{

	public static function run()
	{
		$objDatabase = \Database::getInstance();
		
		$objDatabase->query("ALTER TABLE `tl_page` ADD `rootId` int(10) unsigned NOT NULL default '0'");
		$objDatabase->query("ALTER TABLE `tl_page` ADD `updatechilds` int(1) unsigned NOT NULL default '0'");
		$objDatabase->query("ALTER TABLE `tl_page` ADD `parents` varchar(64) NOT NULL default ''");
		
		$objRootPages = $objDatabase->execute("SELECT id FROM tl_page WHERE pid=0 AND(parents = '' OR parents IS NULL)");

		if($objRootPages->numRows)
		{
			$arrRootPages = $objRootPages->fetchAllAssoc();

			foreach ($arrRootPages as $rootPages)
			{
				ParentListRunOnce::updateChildren($rootPages['id'], '', $rootPages['id']);
				$objDatabase->prepare("UPDATE tl_page SET rootId=?, parents=? WHERE id=?")->execute(0, 0, $rootPages['id']);
			}
		}
	}


	public static function updateChildren($strId, $strParents, $strRootId)
	{
		$objDatabase = \Database::getInstance();
	
		$objChildren = $objDatabase->prepare("SELECT id from tl_page WHERE pid=?")->execute($strId);

		if($objChildren->numRows)
		{

			$arrChildren = $objChildren->fetchAllAssoc();

			if ($strParents != '')
			{
				$strParents = $strId . ',' . $strParents;
			}
			else
			{
				$strParents = $strId;
			}

			foreach ($arrChildren as $children)
			{
				$objDatabase->prepare("UPDATE tl_page SET rootId=?, parents=? WHERE id=?")->execute($strRootId, $strParents, $children['id']);

				ParentListRunOnce::updateChildren($children['id'], $strParents, $strRootId);
			}
		}
	}
}


$objParentListRunOnce = new ParentListRunOnce();
$objParentListRunOnce->run();
