<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

<script>
	const view = {
		refresh_url: null,
		refresh_data: null,

		refresh_simple_url: null,
		refresh_interval: null,
		refresh_counters: null,

		running: false,
		timeout: null,
		_refresh_message_box: null,
		_popup_message_box: null,

		checkbox_object: null,

		init({refresh_url, refresh_data, refresh_interval, filter_options, checkbox_object}) {
			this.refresh_url = new Curl(refresh_url, false);
			this.refresh_data = refresh_data;
			this.refresh_interval = refresh_interval;
			this.checkbox_object = checkbox_object;

			const url = new Curl('zabbix.php', false);
			url.setArgument('action', 'latest.view.refresh');
			this.refresh_simple_url = url.getUrl();

			this.initTabFilter(filter_options);

			if (this.refresh_interval != 0) {
				this.running = true;
				this.scheduleRefresh();
			}
		},

		initTabFilter(filter_options) {
			if (!filter_options) {
				return;
			}

			this.refresh_counters = this.createCountersRefresh(1);
			this.filter = new CTabFilter(document.getElementById('monitoring_latest_filter'), filter_options);

			this.filter.on(TABFILTER_EVENT_URLSET, () => {
				this.reloadPartialAndTabCounters();
			});
		},

		createCountersRefresh(timeout) {
			if (this.refresh_counters) {
				clearTimeout(this.refresh_counters);
				this.refresh_counters = null;
			}

			return setTimeout(() => this.getFiltersCounters(), timeout);
		},

		getFiltersCounters() {
			return $.post(this.refresh_simple_url, {
				filter_counters: 1
			})
			.done((json) => {
				if (json.filter_counters) {
					this.filter.updateCounters(json.filter_counters);
				}
			})
			.always(() => {
				if (this.refresh_interval > 0) {
					this.refresh_counters = this.createCountersRefresh(this.refresh_interval);
				}
			});
		},

		reloadPartialAndTabCounters() {
			this.refresh_url = new Curl('', false);

			this.unscheduleRefresh();
			this.refresh();

			// Filter is not present in Kiosk mode.
			if (this.filter) {
				const filter_item = this.filter._active_item;

				if (this.filter._active_item.hasCounter()) {
					$.post(this.refresh_simple_url, {
						filter_counters: 1,
						counter_index: filter_item._index
					}).done((json) => {
						if (json.filter_counters) {
							filter_item.updateCounter(json.filter_counters.pop());
						}
					});
				}
			}
		},

		getCurrentForm() {
			return $('form[name=items]');
		},

		getCurrentSubfilter() {
			return $('#latest-data-subfilter');
		},

		_addRefreshMessage(messages) {
			this._removeRefreshMessage();

			this._refresh_message_box = $($.parseHTML(messages));
			addMessage(this._refresh_message_box);
		},

		_removeRefreshMessage() {
			if (this._refresh_message_box !== null) {
				this._refresh_message_box.remove();
				this._refresh_message_box = null;
			}
		},

		_addPopupMessage(message_box) {
			this._removePopupMessage();

			this._popup_message_box = message_box;
			addMessage(this._popup_message_box);
		},

		_removePopupMessage() {
			if (this._popup_message_box !== null) {
				this._popup_message_box.remove();
				this._popup_message_box = null;
			}
		},

		refresh() {
			this.setLoading();

			const params = this.refresh_url.getArgumentsObject();
			const exclude = ['action', 'filter_src', 'filter_show_counter', 'filter_custom_time', 'filter_name'];
			const post_data = Object.keys(params)
				.filter(key => !exclude.includes(key))
				.reduce((post_data, key) => {
					if (key === 'subfilter_tags') {
						post_data[key] = {...params[key]};
					}
					else {
						post_data[key] = (typeof params[key] === 'object')
							? [...params[key]].filter(i => i)
							: params[key];
					}

					return post_data;
				}, {});

			var deferred = $.ajax({
				url: this.refresh_simple_url,
				data: post_data,
				type: 'post',
				dataType: 'json'
			});

			return this.bindDataEvents(deferred);
		},

		setLoading() {
			this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
		},

		clearLoading() {
			this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
		},

		doRefresh(body, subfilter) {
			this.getCurrentForm().replaceWith(body);
			this.getCurrentSubfilter().replaceWith(subfilter);
			chkbxRange.init();
		},

		bindDataEvents(deferred) {
			var that = this;

			deferred
				.done(function(response) {
					that.onDataDone.call(that, response);
				})
				.fail(function(jqXHR) {
					that.onDataFail.call(that, jqXHR);
				})
				.always(this.onDataAlways.bind(this));

			return deferred;
		},

		onDataDone(response) {
			this.clearLoading();
			this._removeRefreshMessage();
			this.doRefresh(response.body, response.subfilter);

			if ('messages' in response) {
				this._addRefreshMessage(response.messages);
			}
		},

		onDataFail(jqXHR) {
			// Ignore failures caused by page unload.
			if (jqXHR.status == 0) {
				return;
			}

			this.clearLoading();

			var messages = $(jqXHR.responseText).find('.msg-global');

			if (messages.length) {
				this.getCurrentForm().html(messages);
			}
			else {
				this.getCurrentForm().html(jqXHR.responseText);
			}
		},

		onDataAlways() {
			if (this.running) {
				this.scheduleRefresh();
			}
		},

		scheduleRefresh() {
			this.unscheduleRefresh();

			if (this.refresh_interval > 0) {
				this.timeout = setTimeout((function () {
					this.timeout = null;
					this.refresh();
				}).bind(this), this.refresh_interval);
			}
		},

		unscheduleRefresh() {
			if (this.timeout !== null) {
				clearTimeout(this.timeout);
				this.timeout = null;
			}

			if (this.deferred) {
				this.deferred.abort();
			}
		},

		massCheckNow(button) {
			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.masscheck_now');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({itemids: chkbxRange.getSelectedIds()})
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller. Checkboxes
					 * use uncheckTableRows which only unsets checkboxes from session storage, but not physically
					 * deselects them. Another reason for need for page reload. Instead of page reload, manually
					 * deselect the checkboxes that were selected previously in session storage, but only in case of
					 * success message. In case of error message leave checkboxes checked.
					 */
					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));

						let uncheckids = chkbxRange.getSelectedIds();
						uncheckids = Object.values(uncheckids);

						// This will only unset checkboxes from session storage, but not physically deselect them.
						uncheckTableRows('latest', []);

						// Deselect the previous checkboxes.
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);

						// Reset the buttons in footer and update main checkbox.
						chkbxRange.update(this.checkbox_object);
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');

					// Deselect the "Execute now" button in both success and error cases, since there is no page reload.
					button.blur();

					// Scroll to top to see the success or error message.
					document.querySelector('header').scrollIntoView();
				});
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			this._removePopupMessage();

			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large'
			});

			this.unscheduleRefresh();

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
				this.scheduleRefresh();
			}, {once: true});
		},

		setSubfilter(field) {
			this.filter.setSubfilter(field[0], field[1]);
		},

		unsetSubfilter(field) {
			this.filter.unsetSubfilter(field[0], field[1]);
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				view.refresh();
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					const title = data.success.title;
					let messages = [];

					if ('messages' in data.success) {
						messages = data.success.messages;
					}

					view._addPopupMessage(makeMessageBox('good', messages, title));
				}

				uncheckTableRows('latest');
				view.refresh();
			}
		}
	};
</script>
