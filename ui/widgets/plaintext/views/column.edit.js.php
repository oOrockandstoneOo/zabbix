<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


use Widgets\PlainText\Includes\CWidgetFieldColumnsList;

?>

window.item_history_column_edit = new class {

	#overlay;
	#dialogue;
	#form;
	#$thresholds_table;
	#highlights_table;

	#item_value_type;

	init({form_id, thresholds, highlights, colors, item_value_type}) {
		this.#overlay = overlays_stack.getById('item-history-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		this.#$thresholds_table = document.getElementById('thresholds_table');
		this.#highlights_table = document.getElementById('highlights_table');

		this.#item_value_type = item_value_type;

		// Initialize item multiselect
		$('#itemid').on('change', () => {
			const ms_item_data = jQuery('#itemid').multiSelect('getData');

			if (ms_item_data.length > 0) {
				if (ms_item_data[0].hasOwnProperty('id')) {
					this.#promiseGetItemType(ms_item_data[0].id)
						.then((type) => {
							if (this.#form.isConnected) {
								this.#item_value_type = type;
								this.#updateForm();
							}
						});
				}

				if (ms_item_data[0].hasOwnProperty('name')) {
					const name_field = this.#form.querySelector('[name=name]');

					if (name_field.value === '') {
						name_field.value = ms_item_data[0].name;
					}
				}
			}
			else {
				this.#item_value_type = null;
			}
		});

		colorPalette.setThemeColors(colors);

		// Initialize highlights table
		$(this.#highlights_table).dynamicRows({
			rows: highlights,
			template: '#highlights-row-tmpl',
			allow_empty: true,
			dataCallback: (row_data) => {
				if (!('color' in row_data)) {
					const colors = this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input');
					const used_colors = [];

					for (const color of colors) {
						if (color.value !== '') {
							used_colors.push(color.value);
						}
					}

					row_data.color = colorPalette.getNextColor(used_colors);
				}
			}
		});

		for (const colorpicker of this.#highlights_table.querySelectorAll('tr.form_row input[name$="[color]"]')) {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		}

		$(this.#highlights_table)
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this.#updateForm();
			})
			.on('afterremove.dynamicRows', () => this.#updateForm());

		document.getElementById('display').addEventListener('change', () => {
			this.#updateForm()
		});

		// Initialize thresholds table
		$(this.#$thresholds_table).dynamicRows({
			rows: thresholds,
			template: '#thresholds-row-tmpl',
			allow_empty: true,
			dataCallback: (row_data) => {
				if (!('color' in row_data)) {
					const colors = this.#form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input');
					const used_colors = [];

					for (const color of colors) {
						if (color.value !== '') {
							used_colors.push(color.value);
						}
					}

					row_data.color = colorPalette.getNextColor(used_colors);
				}
			}
		});

		for (const colorpicker of this.#$thresholds_table.querySelectorAll('tr.form_row input[name$="[color]"]')) {
			$(colorpicker).colorpicker({appendTo: $(colorpicker).closest('.input-color-picker')});
		}

		$(this.#$thresholds_table)
			.on('afteradd.dynamicRows', e => {
				const $colorpicker = $('tr.form_row:last input[name$="[color]"]', e.target);

				$colorpicker.colorpicker({appendTo: $colorpicker.closest('.input-color-picker')});

				this.#updateForm();
			})
			.on('afterremove.dynamicRows', () => this.#updateForm())
			.on('change', (e) => e.target.value = e.target.value.trim());

		// Adding field trimming
		const fields_to_trim = ['name', 'max_length', 'min', 'max'];

		for (const name of fields_to_trim) {
			this.#form.querySelector(`[name=${name}`)
				.addEventListener('change', (e) => e.target.value = e.target.value.trim(), {capture: true});
		}

		// Initialize form elements accessibility.
		this.#updateForm();

		this.#form.style.display = '';
		this.#form.querySelector('[name="name"]').focus();

		this.#form.addEventListener('submit', () => {
			this.submit()
		});
	}

	/**
	 * Fetch type of currently selected item.
	 *
	 *
	 * @return {Promise<any>}  Resolved promise will contain item type, or null in case of error or if no item is
	 *                         currently selected.
	 */
	#promiseGetItemType(itemid) {
		if (itemid === null) {
			return Promise.resolve(null);
		}

		const curl = new Curl('jsrpc.php');

		curl.setArgument('method', 'item_value_type.get');
		curl.setArgument('type', <?= PAGE_TYPE_TEXT_RETURN_JSON ?>);
		curl.setArgument('itemid', itemid);

		return fetch(curl.getUrl())
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return parseInt(response.result);
			})
			.catch((exception) => {
				console.log('Could not get item type', exception);

				return null;
			});
	}

	#updateForm() {
		const is_item_numeric_type = this.#item_value_type == <?= ITEM_VALUE_TYPE_FLOAT ?>
			|| this.#item_value_type == <?= ITEM_VALUE_TYPE_UINT64 ?>;

		const text_types = [<?= ITEM_VALUE_TYPE_STR?>, <?= ITEM_VALUE_TYPE_LOG ?>, <?= ITEM_VALUE_TYPE_TEXT ?>];
		const is_item_text_type = !is_item_numeric_type && text_types.some((type) => type == this.#item_value_type);

		const display_value = document.querySelector('[name=display]:checked').value;
		const show_min_max = is_item_numeric_type && display_value == <?= CWidgetFieldColumnsList::DISPLAY_BAR ?>
			|| display_value == <?= CWidgetFieldColumnsList::DISPLAY_INDICATORS?>;

		// Toggle row visibility
		const rows = {
			'js-highlights-row': is_item_text_type,
			'js-display-row': is_item_text_type || is_item_numeric_type,
			'js-single-line-input': is_item_text_type
				&& display_value == <?= CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE ?>,
			'js-min-row': show_min_max,
			'js-max-row': show_min_max,
			'js-thresholds-row': is_item_numeric_type,
			'js-history-row': is_item_numeric_type,
			'js-monospace-row': is_item_text_type,
			'js-local-time-row': this.#item_value_type == <?= ITEM_VALUE_TYPE_LOG ?>,
			'js-display-as-image-row': this.#item_value_type == <?= ITEM_VALUE_TYPE_BINARY ?>
		}

		for (const class_name in rows) {
			const row = this.#form.getElementsByClassName(class_name);

			for (const element of row) {
				element.style.display = rows[class_name] ? '' : 'none';
			}
		}

		// Toggle disable/enable of input fields
		$(this.#highlights_table).toggleClass('disabled', !is_item_text_type);

		if (is_item_numeric_type || is_item_text_type) {
			const visible_values = is_item_numeric_type
				? [<?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>, <?= CWidgetFieldColumnsList::DISPLAY_BAR ?>,
					<?= CWidgetFieldColumnsList::DISPLAY_INDICATORS?>
				]
				: [<?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>, <?= CWidgetFieldColumnsList::DISPLAY_HTML ?>,
					<?= CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE?>
				];

			for (const input of this.#form.querySelectorAll('[name=display]')) {
				const show_input = visible_values.some((value) => value == input.value);

				input.parentElement.style.display = show_input ? '' : 'none';
				input.disabled = !show_input;

				if (!show_input && input.checked) {
					input.checked = false;

					this.#form.querySelector('[name=display][value="<?= CWidgetFieldColumnsList::DISPLAY_AS_IS ?>"]')
						.checked = true;
				}
			}
		}

		$(this.#$thresholds_table).toggleClass('disabled', !is_item_numeric_type);

		const inputs = {
			'max_length': is_item_text_type && display_value == <?= CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE ?>,
			'min': show_min_max,
			'max': show_min_max,
			'history': is_item_numeric_type,
			'monospace_font': is_item_text_type,
			'local_time': this.#item_value_type == <?= ITEM_VALUE_TYPE_LOG ?>,
			'display_as_image': this.#item_value_type == <?= ITEM_VALUE_TYPE_BINARY ?>
		}

		for (const input_name in inputs) {
			for (const input of this.#form.querySelectorAll(`[name=${input_name}`)) {
				input.disabled = !inputs[input_name];
			}
		}
	}

	submit() {
		const curl = new Curl(this.#form.getAttribute('action'));
		const fields = getFormFields(this.#form);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
};
