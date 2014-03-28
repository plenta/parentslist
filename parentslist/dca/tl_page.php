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


/**
 * Table tl_page
 */
$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][] = array('Parentslist', 'updateParentsList');
$GLOBALS['TL_DCA']['tl_page']['config']['oncut_callback'][] = array('Parentslist', 'cutCallback');
$GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][] = array('Parentslist', 'copyCallback');
$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][] = array('Parentslist', 'submitCallback');


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['rootId'] = array
(
	'sql' => "int(10) unsigned NOT NULL default '0'"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['updatechilds'] = array
(
	'sql' => "int(1) unsigned NOT NULL default '0'"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['parents'] = array
(
	'sql' => "varchar(64) NOT NULL default ''"
);


/**
 * Update the parent page tree
 */
class Parentslist extends \Backend
{

	protected $strRootId;


	/**
	 * Update parent list
	 */
	public function updateParentsList(DataContainer $dc)
	{
		// we start with pid, so add pid at the end
		$strParents = $this->getPid($dc->activeRecord->pid);

		$this->Database->prepare("UPDATE tl_page SET rootId=?, parents=? WHERE id=?")->execute($this->strRootId, $strParents, $dc->activeRecord->id);
	}


	/**
	 * Cut and copy
	 */
	private function cutNCopy($myid, DataContainer $dc)
	{
		// get object related to which we insert the new object
		$objBigBrother = $this->Database->prepare("SELECT pid, rootId, parents FROM tl_page WHERE id=?")->limit(1)->execute($this->Input->get('pid'));

		// Insert as sibling of element with ID "pid"
		// Copy the parent list from that element because it is identical for our object
		if($this->Input->get('mode') == 1)
		{
			$newParents = $objBigBrother->parents;
		}

		// Insert as child of element with ID "pid"
		// Copy the partenst list from that element, add the parent itself and us this as parentslist for our object
		elseif($this->Input->get('mode') == 2)
		{
			$newParents = $this->Input->get('pid') . "," . $objBigBrother->parents;
		}

		// update childs after copy on next submit
		$updateChilds = $this->Input->get('childs')?1:0;

		// database update for this object
		$this->Database->prepare("UPDATE tl_page SET rootId=?, parents=?, updatechilds=? WHERE id=?")->execute($objBigBrother->rootId, $newParents, $updateChilds, $myid);

		// Update all childs in case of moving or copy with childs		
		if($this->Input->get('act') == 'cut')
		{
			$this->updateChildren($dc->id, $newParents, $objBigBrother->rootId);
		}
	}


	/**
	 * Callback for "cut" case (called while moving page elements)
	 */
	public function cutCallback(DataContainer $dc)
	{
		$this->cutNCopy($dc->id, $dc);
	}
	 
	 
	/**
	 * Callback for "copy" case 
	 */
	public function copyCallback($insertId, DataContainer $dc)
	{
		$this->cutNCopy($insertId, $dc);
	}


	/**
	 * Callback for "submit" case - used to update possible children after a copy
	 */
	public function submitCallback(DataContainer $dc)
	{
		if($dc->activeRecord->updatechilds)
		{
			// reset flag
			$this->Database->prepare("UPDATE tl_page SET updatechilds=0 WHERE id=?")->execute($dc->id);

			// update childs
			$this->updateChildren($dc->activeRecord->id, $dc->activeRecord->parents, $dc->activeRecord->rootId);
		}
	}


	/**
	 *
	 */
	protected function getPid($id)
	{
		$pid = $this->Database->prepare("SELECT pid FROM tl_page WHERE id=?")->limit(1)->execute($id)->pid;

		if($pid == 0)
		{
			$this->strRootId = $id;
			return $id;
		}
		else
		{
			return $id . ',' . $this->getPid($pid);
		}
	}


	/**
	 *
	 */
	protected function updateChildren($strId, $strParents, $strRootId)
	{
		$objChildren = $this->Database->prepare("SELECT id from tl_page WHERE pid=?")->execute($strId);

		if($objChildren->numRows)
		{
			$arrChildren = $objChildren->fetchAllAssoc();

			foreach ($arrChildren as $children)
			{
				$this->Database->prepare("UPDATE tl_page SET rootId=?, parents=? WHERE id=?")->execute($strRootId, $strId . ',' . $strParents, $children['id']);
				$this->updateChildren($children['id'], $strId . ',' . $strParents, $strRootId);
			}
		}
	}


	/**
	 *
	 */
	public function runonce()
	{
		$objRootPages = $this->Database->execute("SELECT id FROM tl_page WHERE pid=0 AND(parents = '' OR parents IS NULL)");

		if($objRootPages->numRows)
		{
			$arrRootPages = $objRootPages->fetchAllAssoc();

			foreach ($arrRootPages as $rootPages)
			{
				$this->updateChildren($rootPages->id, $rootPages->id, 0);
			}
		}
	}
}
