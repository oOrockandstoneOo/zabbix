<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
		<?php if (isset($page['scripts']) && in_array('flickerfreescreen.js', $page['scripts'])): ?>
			window.flickerfreeScreen.responsiveness = <?php echo SCREEN_REFRESH_RESPONSIVENESS * 1000; ?>;
		<?php endif ?>

		// the chkbxRange.init() method must be called after the inserted post scripts and initializing cookies
		cookie.init();
		chkbxRange.init();
	});

	/**
	 * Toggles filter state and updates title and icons accordingly.
	 *
	 * @param {string} 	idx					User profile index
	 * @param {string} 	value				Value
	 * @param {object} 	idx2				An array of IDs
	 * @param {int} 	profile_type		Profile type
	 */
	function updateUserProfile(idx, value, idx2, profile_type = PROFILE_TYPE_INT) {
		const value_fields = {
			[PROFILE_TYPE_INT]: 'value_int',
			[PROFILE_TYPE_STR]: 'value_str'
		};

		return sendAjaxData('zabbix.php', {
			data: {
				idx: idx,
				[value_fields[profile_type]]: value,
				idx2: idx2,
				action: 'profile.update',
				_csrf_token: <?= json_encode(CCsrfTokenHelper::get('profile')) ?>
			}
		});
	}

	/**
	 * Add object to the list of favorites.
	 */
	function add2favorites(object, objectid) {
		sendAjaxData('zabbix.php', {
			data: {
				object: object,
				objectid: objectid,
				action: 'favorite.create',
				csrf_token: <?= json_encode(CCsrfTokenHelper::get('favorite')) ?>
			}
		});
	}

	/**
	 * Remove object from the list of favorites. Remove all favorites if objectid==0.
	 */
	function rm4favorites(object, objectid) {
		sendAjaxData('zabbix.php', {
			data: {
				object: object,
				objectid: objectid,
				action: 'favorite.delete',
				csrf_token: <?= json_encode(CCsrfTokenHelper::get('favorite')) ?>
			}
		});
	}
</script>
