/**
 * Admin Settings Page
 */

(function($) {
	'use strict';

	var JetGeometrySettings = {
		init: function() {
			this.bindTabs();
			this.bindColorPickers();
			this.bindImportForm();
			this.bindImportExport();
			this.bindSyncButton();
			this.bindDebugTab();
			this.bindCacheMode();
		},

		bindTabs: function() {
			var self = this;
			
			// Ensure all tabs are hidden except the first one
			$('.tab-content').removeClass('active');
			$('.nav-tab').removeClass('nav-tab-active');
			
			// Show first tab by default (or tab from hash)
			var hash = window.location.hash.substring(1);
			var $activeTab = null;
			
			if ( hash ) {
				$activeTab = $('.nav-tab[data-tab="' + hash + '"]');
			}
			
			if ( ! $activeTab || ! $activeTab.length ) {
				$activeTab = $('.nav-tab').first();
			}
			
			if ( $activeTab.length ) {
				var tabId = $activeTab.data('tab');
				$activeTab.addClass('nav-tab-active');
				var $content = $('#' + tabId);
				if ( $content.length ) {
					$content.addClass('active');
				}
			}
			
			// Bind click handlers - use direct event delegation
			$(document).off('click', '.nav-tab').on('click', '.nav-tab', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $tab = $(this);
				var tab = $tab.data('tab');
				
				if ( ! tab ) {
					console.warn('No tab data found for:', this);
					return false;
				}

				// Update tabs
				$('.nav-tab').removeClass('nav-tab-active');
				$tab.addClass('nav-tab-active');

				// Update content
				$('.tab-content').removeClass('active');
				var $targetTab = $('#' + tab);
				if ( $targetTab.length ) {
					$targetTab.addClass('active');
					console.log('Switched to tab:', tab);
				} else {
					console.warn('Tab content not found:', tab);
					return false;
				}

				// Update URL hash
				if ( window.history && window.history.pushState ) {
					window.history.pushState(null, null, '#' + tab);
				} else {
					window.location.hash = tab;
				}
				
				return false;
			});
		},

		bindColorPickers: function() {
			$('.jet-color-picker').wpColorPicker();
		},

		bindImportForm: function() {
			var self = this;

			// Toggle custom URL field
			$('#import-source').on('change', function() {
				if ( $(this).val() === 'custom' ) {
					$('#custom-url-row').show();
					$('#resolution-row').hide();
				} else {
					$('#custom-url-row').hide();
					$('#resolution-row').show();
				}
			});

			// Start import
			$('#start-import').on('click', function(e) {
				e.preventDefault();

				var confirmed = confirm(JetGeometryAdminSettings.i18n.confirmImport);
				if ( ! confirmed ) {
					return;
				}

				self.startImport();
			});

			// Delete country data
			$(document).on('click', '.delete-country-data', function(e) {
				e.preventDefault();

				var termId = $(this).data('term-id');
				var confirmed = confirm('Delete country GeoJSON data?');

				if ( confirmed ) {
					self.deleteCountryData(termId, $(this).closest('tr'));
				}
			});
		},

		startImport: function() {
			var source = $('#import-source').val();
			var resolution = $('#import-resolution').val();
			var region = $('#import-region').val();
			var customUrl = $('#custom-url').val();
			var restUrl = JetGeometrySettings.getRestUrl();

			// Show progress
			$('#import-progress').show();
			$('#import-results').hide();
			$('#start-import').prop('disabled', true);

			// Make request
			$.ajax({
				url: restUrl + 'countries/import',
				method: 'POST',
				data: JSON.stringify({
					source: source,
					resolution: resolution,
					region: region,
					custom_url: customUrl
				}),
				contentType: 'application/json',
				xhrFields: {
					withCredentials: true
				},
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', JetGeometryAdminSettings.nonce);
				},
				success: function(response) {
					$('#import-progress').hide();
					$('#start-import').prop('disabled', false);

					if ( response.success && response.data ) {
						var data = response.data.data || response.data;
						var html = '<li>Imported: ' + data.imported + '</li>';
						html += '<li>Updated: ' + data.updated + '</li>';

						if ( data.errors && data.errors.length > 0 ) {
							html += '<li>Errors: ' + data.errors.length + '</li>';
							html += '<ul>';
							data.errors.forEach(function(error) {
								html += '<li>' + error + '</li>';
							});
							html += '</ul>';
						}

						$('#results-list').html(html);
						$('#import-results').show();

						// Reload page to update table
						setTimeout(function() {
							location.reload();
						}, 2000);
					}
				},
				error: function(xhr) {
					$('#import-progress').hide();
					$('#start-import').prop('disabled', false);

					var message = 'Import failed';
					if ( xhr.responseJSON && xhr.responseJSON.message ) {
						message = xhr.responseJSON.message;
					}

					alert(message);
				}
			});
		},

		deleteCountryData: function(termId, $row) {
			// This would require a delete endpoint
			// For now, just remove the row visually
			$row.fadeOut(function() {
				$(this).remove();
			});

			// In a real implementation, make an AJAX call to delete term meta
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'jet_geometry_delete_country_data',
					term_id: termId,
					nonce: JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce
				},
				success: function(response) {
				}
			});
		},

		bindImportExport: function() {
			var $form = $('#jet-incidents-import-form');
			if ( ! $form.length ) {
				return;
			}

			var $fileInput   = $('#jet-incidents-import-file');
			var $previewBtn  = $('#jet-incidents-import-preview');
			var $previewPane = $('#jet-incidents-import-preview-table');
			var $previewScroll = $previewPane.find('.jet-incidents-preview-scroll');
			var $mappingWrap = $('#jet-incidents-import-mapping');
			var $mappingTableBody = $('#jet-incidents-mapping-table tbody');
			var $startBtn   = $('#jet-incidents-import-start');
			var $resetBtn   = $('#jet-incidents-import-reset');
			var $log        = $('#jet-incidents-import-log');
			var $presetSelect = $('#jet-incidents-mapping-presets');
			var $presetApply  = $('#jet-incidents-mapping-apply');
			var $presetDelete = $('#jet-incidents-mapping-delete');
			var $presetName   = $('#jet-incidents-mapping-name');
			var $presetSave   = $('#jet-incidents-mapping-save');
			var fieldOptions = JetGeometrySettings.getImportFieldOptions();
			var previewData = null;
			var mappingPresets = JetGeometryAdminSettings.mappingPresets || {};

			var lang = JetGeometryAdminSettings.i18n || {};
			var loadingText = lang.previewLoading || 'Generating preview...';
			var noFileText  = lang.noFileSelected || 'Please select a CSV file first.';
			var mappingTitleText = lang.mappingTitleRequired || 'Please map at least one column to Post Title.';
			var customMetaText = lang.customMetaKeyRequired || 'Enter a meta key for the selected custom meta column.';
			var skipLabel = lang.skipOption || 'Skip this column';

			// Initialize Select2 for country dropdown
			var $countrySelect = $('#jet-incidents-import-country');
			if ( $countrySelect.length && typeof $.fn.select2 !== 'undefined' ) {
				$countrySelect.select2({
					placeholder: lang.selectCountry || 'Select a country...',
					allowClear: true,
					width: '100%',
					language: {
						noResults: function() {
							return lang.noResultsFound || 'No results found';
						},
						searching: function() {
							return lang.searching || 'Searching...';
						}
					}
				});
			}
			var customMetaLabel = lang.customMeta || 'Custom Meta Field…';
			var mappingSavedText = lang.mappingSaved || 'Mapping saved.';
			var mappingDeletedText = lang.mappingDeleted || 'Mapping deleted.';
			var mappingNameRequiredText = lang.mappingNameRequired || 'Enter a name for the mapping.';
			var previewFirstText = lang.previewFirst || 'Generate preview and mapping first.';
			var noMappingSelectedText = lang.noMappingSelected || 'No saved mapping selected.';
			var confirmDeleteText = lang.confirmDeleteMapping || 'Delete the selected mapping?';
			var addMappingText = lang.addMappingTarget || 'Add mapping';
			var removeMappingText = lang.removeMappingTarget || 'Remove';
			var selectSavedText = lang.selectSavedMapping || 'Select saved mapping…';
			var importedItemsLabel = lang.importedItemsLabel || 'Imported items';
			var skippedItemsLabel = lang.skippedItemsLabel || 'Skipped items';
			var rowLabel = lang.rowLabel || 'Row';
			var titleLabel = lang.titleLabel || 'Title';
			var reasonLabel = lang.reasonLabel || 'Reason';

			var updatePresetSelect = function() {
				if ( !$presetSelect.length ) {
					return;
				}
				var current = $presetSelect.val();
				$presetSelect.empty();
				$presetSelect.append( $('<option></option>').val('').text(selectSavedText) );
				$.each(mappingPresets, function(slug, preset) {
					if ( preset && preset.name ) {
						$presetSelect.append( $('<option></option>').val(slug).text(preset.name) );
					}
				});
				if ( current && mappingPresets[current] ) {
					$presetSelect.val(current);
				}
			};

			var renderPreview = function(headers, rows) {
				if ( ! headers || ! headers.length ) {
					$previewPane.hide();
					return;
				}

				var maxRows = Math.min( rows.length, 5 );
				var table = $('<table class="widefat"><thead></thead><tbody></tbody></table>');
				var headRow = $('<tr></tr>');
				headers.forEach(function(label) {
					headRow.append( $('<th></th>').text(label) );
				});
				table.find('thead').append(headRow);

				for ( var i = 0; i < maxRows; i++ ) {
					var dataRow = $('<tr></tr>');
					headers.forEach(function(_, index) {
						var cellValue = (rows[i] && typeof rows[i][index] !== 'undefined') ? rows[i][index] : '';
						dataRow.append( $('<td></td>').text(cellValue) );
					});
					table.find('tbody').append(dataRow);
				}

				$previewScroll.empty().append(table);
				$previewPane.show();
			};

			var renderMapping = function(headers, rows) {
				$mappingTableBody.empty();
				headers.forEach(function(label, index) {
					var row = $('<tr></tr>').attr('data-column', index);
					row.append( $('<td class="column-title"></td>').text(label) );

					var targetsContainer = $('<div class="jet-mapping-targets"></div>');
					var addTargetButton = $('<button type="button" class="button jet-mapping-add"></button>').text(addMappingText);
					var mappingCell = $('<td class="column-mapping"></td>').append(targetsContainer).append(addTargetButton);
					row.append(mappingCell);

					var sampleValue = '';
					for ( var r = 0; r < rows.length; r++ ) {
						if ( rows[r] && typeof rows[r][index] !== 'undefined' && rows[r][index] !== '' ) {
							sampleValue = rows[r][index];
							break;
						}
					}
					row.append( $('<td class="column-sample"></td>').text(sampleValue) );

					var refreshRemoveButtons = function() {
						var count = targetsContainer.find('.jet-mapping-target').length;
						targetsContainer.find('.jet-mapping-remove').toggle(count > 1);
					};

					var addTargetControl = function(targetValue, metaValue) {
						targetValue = targetValue || '';
						metaValue = metaValue || '';

						var targetWrap = $('<div class="jet-mapping-target"></div>');
						var select = $('<select class="jet-incidents-mapping-select"></select>');
						select.append( $('<option></option>').val('').text(skipLabel) );
						fieldOptions.forEach(function(option) {
							select.append( $('<option></option>').val(option.value).text(option.label) );
						});

						var customMetaInput = $('<input type="text" class="jet-custom-meta-key" placeholder="meta_key">').hide();
						var removeButton = $('<button type="button" class="button-link jet-mapping-remove"></button>').text(removeMappingText);

						select.on('change', function() {
							if ( 'custom_meta' === $(this).val() ) {
								customMetaInput.show().focus();
							} else {
								customMetaInput.hide().val('');
							}
						});

						removeButton.on('click', function(e) {
							e.preventDefault();
							targetWrap.remove();
							refreshRemoveButtons();
						});

						targetWrap.append(select).append(customMetaInput).append(removeButton);
						targetsContainer.append(targetWrap);

						if ( 'custom_meta' === targetValue ) {
							select.val('custom_meta').trigger('change');
							if ( metaValue ) {
								customMetaInput.val(metaValue).show();
							}
						} else if ( targetValue ) {
							select.val(targetValue).trigger('change');
						} else {
							select.val('');
						}

						refreshRemoveButtons();
						return targetWrap;
					};

					var setupTargets = function(targets) {
						targetsContainer.empty();
						if ( ! targets || ! targets.length ) {
							addTargetControl('', '');
						} else {
							targets.forEach(function(targetString) {
								var targetValue = targetString;
								var metaValue = '';
								if ( targetValue.indexOf('meta:') === 0 ) {
									metaValue = targetValue.substring(5);
									targetValue = 'custom_meta';
								}
								addTargetControl(targetValue, metaValue);
							});
						}
						refreshRemoveButtons();
					};

					addTargetButton.on('click', function(e) {
						e.preventDefault();
						addTargetControl('', '');
					});

					row.data('setupTargets', setupTargets);
					setupTargets([]);

					$mappingTableBody.append(row);
				});

				$mappingWrap.show();
			};

			var resetImportUI = function() {
				previewData = null;
				$previewPane.hide();
				$previewScroll.empty();
				$mappingWrap.hide();
				$mappingTableBody.empty();
				$log.empty().removeClass('notice notice-success notice-error');
			};

			var collectMapping = function(requireTitle) {
				requireTitle = (typeof requireTitle === 'undefined') ? false : requireTitle;
				var mapping = [];
				var hasTitle = false;
				var metaError = false;

				$mappingTableBody.find('tr').each(function() {
					var $row = $(this);
					var columnIndex = parseInt($row.data('column'), 10);
					var headerLabel = $row.find('.column-title').text().trim();

					$row.find('.jet-mapping-target').each(function() {
						var $targetWrap = $(this);
						var selectVal = $targetWrap.find('select').val();
						var customMetaKey = $targetWrap.find('.jet-custom-meta-key').val().trim();

						if ( ! selectVal ) {
							return;
						}

						var targetValue = selectVal;
						if ( 'custom_meta' === selectVal ) {
							if ( ! customMetaKey ) {
								metaError = true;
								return false;
							}
							targetValue = 'meta:' + customMetaKey;
						}

						if ( 'post_title' === targetValue ) {
							hasTitle = true;
						}

						mapping.push({
							column: columnIndex,
							target: targetValue,
							header: headerLabel
						});
					});

					if ( metaError ) {
						return false;
					}
				});

				if ( requireTitle && ! hasTitle ) {
					return { error: 'title' };
				}

				return {
					mapping: mapping,
					hasTitle: hasTitle
				};
			};

			var applyPreset = function(slug) {
				if ( ! previewData ) {
					alert(previewFirstText);
					return;
				}

				if ( ! slug || ! mappingPresets[slug] ) {
					alert(noMappingSelectedText);
					return;
				}

				var preset = mappingPresets[slug];
				var grouped = {};
				(preset.mapping || []).forEach(function(item) {
					if ( item.header && item.target ) {
						if ( ! grouped[item.header] ) {
							grouped[item.header] = [];
						}
						grouped[item.header].push(item.target);
					}
				});

				$mappingTableBody.find('tr').each(function() {
					var $row = $(this);
					var headerLabel = $row.find('.column-title').text().trim();
					var setupTargets = $row.data('setupTargets');
					var targets = grouped[headerLabel] || [];
					if ( typeof setupTargets === 'function' ) {
						setupTargets(targets);
					}
				});
			};

			var savePreset = function(name, mapping) {
				var formData = new FormData();
				formData.append('action', 'jet_geometry_save_incident_mapping');
				formData.append('nonce', JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce);
				formData.append('name', name);
				formData.append('mapping', JSON.stringify(mapping));

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if ( response && response.success && response.data && response.data.presets ) {
							mappingPresets = response.data.presets;
							updatePresetSelect();
							if ( response.data.slug ) {
								$presetSelect.val(response.data.slug);
							}
							$log.removeClass('notice notice-error notice-success').addClass('notice notice-success').text(mappingSavedText);
						} else {
							$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text((response && response.data) ? response.data : 'Error saving mapping.');
						}
					},
					error: function(xhr) {
						var message = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Error saving mapping.';
						$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text(message);
					}
				});
			};

			var deletePreset = function(slug) {
				var formData = new FormData();
				formData.append('action', 'jet_geometry_delete_incident_mapping');
				formData.append('nonce', JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce);
				formData.append('slug', slug);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if ( response && response.success && response.data && response.data.presets ) {
							mappingPresets = response.data.presets;
							updatePresetSelect();
							$presetSelect.val('');
							$log.removeClass('notice notice-error notice-success').addClass('notice notice-success').text(mappingDeletedText);
						} else {
							$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text((response && response.data) ? response.data : 'Error deleting mapping.');
						}
					},
					error: function(xhr) {
						var message = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Error deleting mapping.';
						$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text(message);
					}
				});
			};

			updatePresetSelect();

			$previewBtn.on('click', function(e) {
				e.preventDefault();

				if ( !$fileInput[0].files.length ) {
					alert(noFileText);
					return;
				}

				$log.removeClass('notice notice-error notice-success').text(loadingText);
				$mappingWrap.hide();
				$previewPane.hide();

				var formData = new FormData();
				formData.append('action', 'jet_geometry_preview_incidents');
				formData.append('nonce', JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce);
				formData.append('import_file', $fileInput[0].files[0]);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if ( response && response.success && response.data ) {
							previewData = response.data;
							renderPreview(previewData.headers || [], previewData.rows || []);
							renderMapping(previewData.headers || [], previewData.rows || []);
							$log.addClass('notice notice-success').text(lang.previewReady || 'Preview generated. Map the columns below.');
						} else {
							$log.addClass('notice notice-error').text((response && response.data) ? response.data : 'Preview failed.');
						}
					},
					error: function(xhr) {
						var message = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Preview failed.';
						$log.addClass('notice notice-error').text(message);
					}
				});
			});

			$startBtn.on('click', function(e) {
				e.preventDefault();
				if ( ! previewData ) {
					alert(lang.previewFirst || 'Generate preview and mapping first.');
					return;
				}

				var mappingResult = collectMapping(true);
				if ( mappingResult.error === 'meta' ) {
					alert(customMetaText);
					return;
				}
				if ( mappingResult.error === 'title' || ! mappingResult.mapping.length ) {
					alert(mappingTitleText);
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jet_geometry_import_incidents');
				formData.append('nonce', JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce);
				formData.append('mapping', JSON.stringify(mappingResult.mapping.map(function(item) {
					return { column: item.column, target: item.target };
				})));
				formData.append('import_file', $fileInput[0].files[0]);
				formData.append('default_country', $('#jet-incidents-import-country').val() || '');
				formData.append('duplicate_action', $('#jet-incidents-duplicate-action').val() || 'skip');
				formData.append('post_status', $('#jet-incidents-post-status').val() || 'draft');
				formData.append('update_status', $('#jet-incidents-update-status').is(':checked') ? '1' : '0');

				$startBtn.prop('disabled', true);
				
				// Create progress bar
				var progressHtml = $('<div class="jet-import-progress" style="margin: 15px 0;"></div>');
				var progressBar = $('<div class="jet-progress-bar" style="width: 100%; height: 30px; background: #f0f0f0; border-radius: 4px; overflow: hidden; position: relative;"></div>');
				var progressFill = $('<div class="jet-progress-fill" style="height: 100%; background: #2271b1; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div>');
				var progressText = $('<div class="jet-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; font-weight: bold; color: #333; z-index: 1;">0%</div>');
				
				progressBar.append(progressFill);
				progressBar.append(progressText);
				progressHtml.append(progressBar);
				
				$log.removeClass('notice notice-error notice-success').empty().append(
					$('<p></p>').text(lang.importing || 'Importing...')
				).append(progressHtml);

				// Start polling for progress
				var progressInterval = null;
				var checkProgress = function() {
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'jet_geometry_import_progress',
							nonce: JetGeometryAdminSettings.ajaxNonce || JetGeometryAdminSettings.nonce
						},
						success: function(response) {
							if ( response && response.success && response.data ) {
								var progress = response.data;
								var percent = Math.round(progress.percent || 0);
								
								progressFill.css('width', percent + '%');
								progressText.text(percent + '% (' + (progress.current || 0) + ' / ' + (progress.total || 0) + ')');
								
								if ( progress.status === 'completed' ) {
									if ( progressInterval ) {
										clearInterval(progressInterval);
									}
								}
							}
						},
						error: function() {
							// Silently fail - progress is optional
						}
					});
				};
				
				// Start checking progress every 500ms
				progressInterval = setInterval(checkProgress, 500);
				checkProgress(); // Check immediately

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						// Stop progress polling
						if ( progressInterval ) {
							clearInterval(progressInterval);
						}
						
						// Update progress to 100%
						progressFill.css('width', '100%');
						progressText.text('100%');
						
						$startBtn.prop('disabled', false);
						if ( response && response.success && response.data ) {
							var summary = response.data;
							var message = (lang.importCompleted || 'Import completed.') + ' Imported: ' + (summary.imported || 0);
							if ( summary.updated && summary.updated > 0 ) {
								message += ', Updated: ' + summary.updated;
							}
							message += ', Skipped: ' + (summary.skipped || 0);
							if ( summary.geocoded && summary.geocoded > 0 ) {
								message += ', Geocoded: ' + summary.geocoded;
							}
							if ( summary.geocoding_failed && summary.geocoding_failed > 0 ) {
								message += ', Geocoding failed: ' + summary.geocoding_failed;
							}
							var summaryHtml = $('<div class="jet-import-summary"></div>');
							summaryHtml.append( $('<p></p>').text(message) );

							if ( summary.imported_items && summary.imported_items.length ) {
								var importedDetails = $('<details open class="jet-import-details"></details>');
								importedDetails.append( $('<summary></summary>').text(importedItemsLabel) );
								var importedList = $('<ul></ul>');
								summary.imported_items.forEach(function(item) {
									var parts = [];
									parts.push(rowLabel + ': ' + (item.row || '?'));
									if ( item.title ) {
										parts.push(titleLabel + ': ' + item.title);
									}
									if ( item.post_id ) {
										parts.push('ID: ' + item.post_id);
									}
									if ( item.date ) {
										parts.push('Date: ' + item.date);
									}
									importedList.append( $('<li></li>').text(parts.join(' — ')) );
								});
								importedDetails.append(importedList);
								summaryHtml.append(importedDetails);
							}

							if ( summary.updated_items && summary.updated_items.length ) {
								var updatedDetails = $('<details open class="jet-import-details"></details>');
								updatedDetails.append( $('<summary></summary>').text(lang.updatedItemsLabel || 'Updated items') );
								var updatedList = $('<ul></ul>');
								summary.updated_items.forEach(function(item) {
									var parts = [];
									parts.push(rowLabel + ': ' + (item.row || '?'));
									if ( item.title ) {
										parts.push(titleLabel + ': ' + item.title);
									}
									if ( item.post_id ) {
										parts.push('ID: ' + item.post_id);
									}
									if ( item.date ) {
										parts.push('Date: ' + item.date);
									}
									updatedList.append( $('<li></li>').text(parts.join(' — ')) );
								});
								updatedDetails.append(updatedList);
								summaryHtml.append(updatedDetails);
							}

							if ( summary.skipped_items && summary.skipped_items.length ) {
								var skippedDetails = $('<details open class="jet-import-details"></details>');
								skippedDetails.append( $('<summary></summary>').text(skippedItemsLabel) );
								var skippedList = $('<ul></ul>');
								summary.skipped_items.forEach(function(item) {
									var parts = [];
									parts.push(rowLabel + ': ' + (item.row || '?'));
									if ( item.title ) {
										parts.push(titleLabel + ': ' + item.title);
									}
									if ( item.reason ) {
										parts.push(reasonLabel + ': ' + item.reason);
									}
									skippedList.append( $('<li></li>').text(parts.join(' — ')) );
								});
								skippedDetails.append(skippedList);
								summaryHtml.append(skippedDetails);
							}

							if ( summary.errors && summary.errors.length ) {
								var errorsDetails = $('<details class="jet-import-details"></details>');
								errorsDetails.append( $('<summary></summary>').text('Errors') );
								var errorsList = $('<ul></ul>');
								summary.errors.forEach(function(msg) {
									errorsList.append( $('<li></li>').text(msg) );
								});
								errorsDetails.append(errorsList);
								summaryHtml.append(errorsDetails);
							}

							// Detailed changelog
							if ( summary.changelog && summary.changelog.length ) {
								var changelogDetails = $('<details class="jet-import-details" style="margin-top: 20px;"></details>');
								changelogDetails.append( $('<summary style="font-weight: bold; font-size: 14px;"></summary>').text(lang.changelogLabel || 'Detailed Changelog') );
								
								var changelogContainer = $('<div class="jet-import-changelog" style="max-height: 500px; overflow-y: auto; margin-top: 10px;"></div>');
								
								// Group changelog by row
								var changelogByRow = {};
								summary.changelog.forEach(function(entry) {
									var row = entry.row || 0;
									if ( ! changelogByRow[row] ) {
										changelogByRow[row] = [];
									}
									changelogByRow[row].push(entry);
								});
								
								// Sort rows
								var sortedRows = Object.keys(changelogByRow).sort(function(a, b) {
									return parseInt(a) - parseInt(b);
								});
								
								sortedRows.forEach(function(rowNum) {
									var rowEntries = changelogByRow[rowNum];
									var rowSection = $('<div class="jet-changelog-row" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;"></div>');
									rowSection.append( $('<div style="font-weight: bold; margin-bottom: 8px; color: #2271b1;"></div>').text('Row ' + rowNum) );
									
									var rowList = $('<ul style="margin: 0; padding-left: 20px;"></ul>');
									rowEntries.forEach(function(entry) {
										var icon = '';
										var color = '#666';
										
										if ( entry.action === 'post_created' || entry.action === 'category_created' || entry.action === 'geometry_created' ) {
											icon = '✓';
											color = '#00a32a';
										} else if ( entry.action === 'post_updated' || entry.action === 'category_updated' || entry.action === 'geometry_updated' || entry.action === 'meta_updated' || entry.action === 'taxonomy_assigned' ) {
											icon = '↻';
											color = '#2271b1';
										} else if ( entry.action === 'post_found' || entry.action === 'category_found' ) {
											icon = '→';
											color = '#646970';
										} else if ( entry.action === 'post_skipped' ) {
											icon = '⊘';
											color = '#dba617';
										} else if ( entry.action === 'post_error' || entry.action === 'category_error' || entry.action === 'geocoding_failed' ) {
											icon = '✗';
											color = '#d63638';
										} else {
											icon = '•';
										}
										
										var listItem = $('<li style="margin-bottom: 5px; color: ' + color + ';"></li>');
										var messageContainer = $('<span style="display: inline-block;"></span>');
										messageContainer.append( $('<span style="font-weight: bold; margin-right: 5px;"></span>').text(icon) );
										messageContainer.append( $('<span></span>').text(entry.message || 'No message') );
										
										// Add "Edit post" button if post_id exists
										if ( entry.post_id ) {
											var editButton = $('<a></a>')
												.attr('href', JetGeometryAdminSettings.adminUrl + 'post.php?post=' + entry.post_id + '&action=edit')
												.attr('target', '_blank')
												.addClass('button button-small')
												.css({
													'margin-left': '10px',
													'vertical-align': 'middle',
													'font-size': '11px',
													'padding': '2px 8px',
													'height': 'auto',
													'line-height': '1.5'
												})
												.text(lang.editPost || 'Edit post');
											messageContainer.append(editButton);
										}
										
										listItem.append(messageContainer);
										rowList.append(listItem);
									});
									rowSection.append(rowList);
									changelogContainer.append(rowSection);
								});
								
								changelogDetails.append(changelogContainer);
								summaryHtml.append(changelogDetails);
							}

							$log.removeClass('notice notice-error notice-success').addClass('notice notice-success').empty().append(summaryHtml);
						} else {
							$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text((response && response.data) ? response.data : (lang.importError || 'Import failed.'));
						}
					},
					error: function(xhr) {
						// Stop progress polling
						if ( progressInterval ) {
							clearInterval(progressInterval);
						}
						
						$startBtn.prop('disabled', false);
						var message = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : (lang.importError || 'Import failed.');
						$log.removeClass('notice notice-error notice-success').addClass('notice notice-error').text(message);
					}
				});
			});

			$resetBtn.on('click', function(e) {
				e.preventDefault();
				$fileInput.val('');
				$('#jet-incidents-import-country').val('');
				$('#jet-incidents-duplicate-action').val('update');
				$('#jet-incidents-post-status').val('draft');
				$('#jet-incidents-update-status').prop('checked', false);
				resetImportUI();
			});

			$fileInput.on('change', function() {
				resetImportUI();
			});

			$presetSave.on('click', function(e) {
				e.preventDefault();
				if ( ! previewData ) {
					alert(previewFirstText);
					return;
				}

				var name = $presetName.val().trim();
				if ( ! name ) {
					alert(mappingNameRequiredText);
					return;
				}

				var mappingResult = collectMapping(true);
				if ( mappingResult.error === 'meta' ) {
					alert(customMetaText);
					return;
				}
				if ( mappingResult.error === 'title' || ! mappingResult.mapping.length ) {
					alert(mappingTitleText);
					return;
				}

				var presetMapping = mappingResult.mapping.map(function(item) {
					return {
						header: item.header,
						target: item.target
					};
				});

				savePreset(name, presetMapping);
			});

			$presetApply.on('click', function(e) {
				e.preventDefault();
				var slug = $presetSelect.val();
				applyPreset(slug);
			});

			$presetDelete.on('click', function(e) {
				e.preventDefault();
				var slug = $presetSelect.val();
				if ( ! slug ) {
					alert(noMappingSelectedText);
					return;
				}
				if ( ! window.confirm(confirmDeleteText) ) {
					return;
				}
				deletePreset(slug);
			});
		}
	};

	JetGeometrySettings.getRestUrl = function() {
		var restUrl = JetGeometryAdminSettings.restUrl || '';

		try {
			var currentOrigin = window.location.origin || '';

			if ( currentOrigin && restUrl.indexOf(currentOrigin) !== 0 ) {
				var parsed = new URL(restUrl);
				restUrl = currentOrigin + parsed.pathname;
			}
		} catch (error) {
		}

		if ( restUrl.slice(-1) !== '/' ) {
			restUrl += '/';
		}

		return restUrl;
	};

	JetGeometrySettings.getImportFieldOptions = function() {
		var lang = JetGeometryAdminSettings.i18n || {};
		return [
			{ value: 'post_title', label: 'Post Title' },
			{ value: 'post_content', label: 'Post Content' },
			{ value: 'taxonomy:incident-type', label: 'Incident Type (taxonomy)' },
			{ value: 'taxonomy:incident-subtype', label: 'Incident Subcategory (taxonomy)' },
			{ value: 'taxonomy:countries', label: 'Country (taxonomy)' },
			{ value: 'incident_year', label: 'Incident Year (for post date)' },
			{ value: 'incident_month', label: 'Incident Month (for post date)' },
			{ value: 'incident_day', label: 'Incident Day (for post date)' },
			{ value: 'meta:_incident_location', label: 'Location (meta)' },
			{ value: 'meta:link_to_the_source', label: 'Link to the Source (meta)' },
			{ value: 'meta:_incident_summary', label: 'Short Description (meta)' },
			{ value: 'custom_meta', label: lang.customMeta || 'Custom Meta Field…' }
		];
	};

	JetGeometrySettings.bindSyncButton = function() {
		$('#jet-geometry-sync-all').on('click', function(e) {
			e.preventDefault();
			var confirmed = confirm('This will synchronize geometry data for all incident posts. This may take a while. Continue?');
			if ( ! confirmed ) {
				return;
			}
			JetGeometrySettings.syncAllPosts();
		});
	};

	JetGeometrySettings.syncAllPosts = function() {
		var $button = $('#jet-geometry-sync-all');
		var $spinner = $('#jet-geometry-sync-spinner');
		var $progress = $('#jet-geometry-sync-progress');
		var $progressBar = $('#jet-geometry-sync-progress-bar');
		var $progressPercent = $('#jet-geometry-sync-percent');
		var $status = $('#jet-geometry-sync-status');
		var $currentBatch = $('#jet-geometry-sync-current-batch');
		var $results = $('#jet-geometry-sync-results');
		var $resultsSummary = $('#jet-geometry-sync-results-summary');
		var $resultsDetails = $('#jet-geometry-sync-results-details');

		$button.prop('disabled', true);
		$spinner.css('visibility', 'visible');
		$progress.show();
		$results.hide();
		$status.text('Initializing...');
		$progressBar.css('width', '0%');
		$progressPercent.text('0%');

		var offset = 0;
		var total = 0;
		var stats = {
			synced: 0,
			generated: 0,
			skipped: 0,
			errors: []
		};
		var allDetails = []; // Store all post details

		function processBatch() {
			// Get AJAX URL - WordPress admin should have ajaxurl, but use fallback if not
			var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : (JetGeometryAdminSettings.ajaxUrl || '/wp-admin/admin-ajax.php');
			
			if ( ! ajaxUrl ) {
				$status.html('<strong style="color: #d63638;">Error: AJAX URL is not available. Please refresh the page.</strong>');
				$button.prop('disabled', false);
				$spinner.css('visibility', 'hidden');
				console.error('[JetGeometry] AJAX URL is not available');
				return;
			}

			console.log('[JetGeometry] Sending AJAX request:', {
				url: ajaxUrl,
				action: 'jet_geometry_sync_all_posts',
				offset: offset,
				total: total,
				batch_size: 10
			});

			$.ajax({
				url: ajaxUrl,
				method: 'POST',
				data: {
					action: 'jet_geometry_sync_all_posts',
					offset: offset,
					total: total,
					batch_size: 10,
					nonce: JetGeometryAdminSettings.ajaxNonce
				},
				success: function(response) {
					console.log('[JetGeometry] AJAX response:', response);
					if ( ! response.success ) {
						var errorMsg = response.data || 'Unknown error';
						$status.html('<strong style="color: #d63638;">Error: ' + errorMsg + '</strong>');
						$button.prop('disabled', false);
						$spinner.css('visibility', 'hidden');
						console.error('[JetGeometry] AJAX error:', errorMsg);
						return;
					}

					var data = response.data;

					// First request - get total and process first batch
					if ( 0 === offset && 0 === total ) {
						total = data.total;
						if ( total === 0 ) {
							$status.text('No posts found that need synchronization.');
							$button.prop('disabled', false);
							$spinner.css('visibility', 'hidden');
							return;
						}
						// Total is set, continue processing response data below
					}

					// Update stats
					if ( data.synced ) stats.synced += data.synced;
					if ( data.generated ) stats.generated += data.generated;
					if ( data.skipped ) stats.skipped += data.skipped;
					if ( data.errors && data.errors.length ) {
						stats.errors = stats.errors.concat(data.errors);
					}

					// Collect details
					if ( data.details && data.details.length ) {
						allDetails = allDetails.concat(data.details);
					}

					// Update offset only if we have processed data
					if ( data.offset !== undefined ) {
						offset = data.offset;
					}
					var percent = total > 0 ? Math.min(Math.round((offset / total) * 100), 100) : 0;
					$progressBar.css('width', percent + '%');
					$progressPercent.text(percent + '%');
					
					var statusText = 'Processing... ' + offset + ' / ' + total + ' posts';
					$status.html('<strong>' + statusText + '</strong><br>' +
						'<span style="color: #2271b1;">✓ Synced: ' + stats.synced + '</span> | ' +
						'<span style="color: #00a32a;">+ Generated: ' + stats.generated + '</span> | ' +
						'<span style="color: #666;">⊘ Skipped: ' + stats.skipped + '</span>');

					// Show current batch details
					if ( data.details && data.details.length ) {
						var batchInfo = 'Current batch: ';
						var batchParts = [];
						data.details.forEach(function(detail) {
							var icon = '';
							var color = '#666';
							if ( detail.action === 'synced' ) {
								icon = '✓';
								color = '#2271b1';
							} else if ( detail.action === 'generated' ) {
								icon = '+';
								color = '#00a32a';
							} else if ( detail.action === 'error' ) {
								icon = '✗';
								color = '#d63638';
							} else {
								icon = '⊘';
							}
							batchParts.push('<span style="color: ' + color + ';">' + icon + ' #' + detail.id + '</span>');
						});
						$currentBatch.html(batchInfo + batchParts.join(', '));
					}

					if ( data.continue ) {
						processBatch();
					} else {
						// Done
						$progressBar.css('width', '100%');
						$progressPercent.text('100%');
						$status.html('<strong style="color: #00a32a;">✓ Completed! Processed ' + offset + ' posts.</strong>');
						$currentBatch.text('');
						$button.prop('disabled', false);
						$spinner.css('visibility', 'hidden');

						// Show summary
						var summaryHtml = '<div class="notice notice-success"><p><strong>Synchronization completed!</strong></p><ul>';
						summaryHtml += '<li><strong>Synced existing geometry:</strong> ' + stats.synced + ' posts</li>';
						summaryHtml += '<li><strong>Generated new geometry:</strong> ' + stats.generated + ' posts</li>';
						summaryHtml += '<li><strong>Skipped (already complete):</strong> ' + stats.skipped + ' posts</li>';
						if ( stats.errors.length > 0 ) {
							summaryHtml += '<li><strong>Errors:</strong> ' + stats.errors.length + '</li>';
						}
						summaryHtml += '</ul></div>';
						$resultsSummary.html(summaryHtml);

						// Show detailed debug
						var detailsHtml = '<div style="margin-bottom: 10px;"><strong>Detailed Log (' + allDetails.length + ' posts):</strong></div>';
						allDetails.forEach(function(detail, index) {
							var icon = '';
							var color = '#666';
							var bgColor = '#f9f9f9';
							
							if ( detail.action === 'synced' ) {
								icon = '✓';
								color = '#2271b1';
								bgColor = '#e5f5fa';
							} else if ( detail.action === 'generated' ) {
								icon = '＋';
								color = '#00a32a';
								bgColor = '#e5f5e5';
							} else if ( detail.action === 'error' ) {
								icon = '✗';
								color = '#d63638';
								bgColor = '#fce8e8';
							} else {
								icon = '⊘';
								bgColor = '#f5f5f5';
							}

							detailsHtml += '<div style="padding: 8px; margin-bottom: 4px; background: ' + bgColor + '; border-left: 3px solid ' + color + ';">';
							detailsHtml += '<span style="color: ' + color + '; font-weight: bold;">' + icon + ' [' + detail.action.toUpperCase() + ']</span> ';
							detailsHtml += '<strong>Post #' + detail.id + ':</strong> ' + (detail.title || '(no title)') + '<br>';
							detailsHtml += '<span style="margin-left: 20px; color: #666;">' + detail.message + '</span>';
							if ( detail.location ) {
								detailsHtml += '<br><span style="margin-left: 20px; color: #666;">📍 Location: ' + detail.location + '</span>';
							}
							if ( detail.coordinates ) {
								detailsHtml += '<br><span style="margin-left: 20px; color: #666;">🗺️ Coordinates: ' + detail.coordinates + '</span>';
							}
							if ( detail.hidden_meta ) {
								var hiddenMetaColor = detail.hidden_meta.indexOf('✗') !== -1 ? '#d63638' : '#00a32a';
								detailsHtml += '<br><span style="margin-left: 20px; color: ' + hiddenMetaColor + '; font-weight: bold;">🔧 Hidden Meta: ' + detail.hidden_meta + '</span>';
							}
							detailsHtml += '</div>';
						});
						$resultsDetails.html(detailsHtml);
						$results.show();
					}
				},
				error: function(xhr, status, error) {
					console.error('[JetGeometry] AJAX request failed:', {
						status: status,
						error: error,
						responseText: xhr.responseText,
						statusCode: xhr.status
					});
					var errorMsg = 'Request failed';
					if ( xhr.status === 0 ) {
						errorMsg = 'Connection error - check if WordPress is running';
					} else if ( xhr.status === 403 ) {
						errorMsg = 'Permission denied - check nonce';
					} else if ( xhr.status === 500 ) {
						errorMsg = 'Server error - check PHP error logs';
						if ( xhr.responseText ) {
							console.error('[JetGeometry] Server response:', xhr.responseText);
						}
					} else {
						errorMsg = 'Error ' + xhr.status + ': ' + error;
					}
					$status.html('<strong style="color: #d63638;">Error: ' + errorMsg + '</strong>');
					$button.prop('disabled', false);
					$spinner.css('visibility', 'hidden');
				}
			});
		}

		processBatch();
	};

	JetGeometrySettings.bindDebugTab = function() {
		var self = this;
		var currentPage = 1;
		var perPage = 100;

		// Start debug button
		$(document).on('click', '#jet-debug-start', function() {
			if ( window.JetGeometrySettingsDebugAnalyzing ) {
				return;
			}
			currentPage = 1;
			self.startDebugAnalysis();
		});

		// Refresh button
		$(document).on('click', '#jet-debug-refresh', function() {
			if ( window.JetGeometrySettingsDebugAnalyzing ) {
				return;
			}
			currentPage = 1;
			self.startDebugAnalysis();
		});

		// Download JSON button
		$(document).on('click', '#jet-debug-download', function() {
			if ( !window.JetGeometrySettingsDebugPosts || window.JetGeometrySettingsDebugPosts.length === 0 ) {
				alert('No debug data available. Please run analysis first.');
				return;
			}

			var $button = $(this);
			var originalText = $button.text();
			$button.prop('disabled', true).text('Saving...');

			// Get filtered posts if filter is active
			var selectedCountry = $('#jet-debug-country-filter').val();
			var postsToExport = selectedCountry ? 
				window.JetGeometrySettingsDebugPosts.filter(function(post) {
					return post.country && post.country.indexOf(selectedCountry) !== -1;
				}) : 
				window.JetGeometrySettingsDebugPosts;

			// Create JSON data
			var jsonData = {
				generated_at: new Date().toISOString(),
				total_posts: window.JetGeometrySettingsDebugPosts.length,
				filtered_posts: postsToExport.length,
				filter_applied: $('#jet-debug-country-filter').val() || null,
				posts: postsToExport
			};

			// Convert to JSON string with pretty formatting
			var jsonString = JSON.stringify(jsonData, null, 2);

			// First, save to server
			$.ajax({
				url: JetGeometryAdminSettings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'jet_geometry_save_debug_json',
					nonce: JetGeometryAdminSettings.ajaxNonce,
					json_data: jsonString
				},
				success: function(response) {
					if ( response.success ) {
						console.log('[JetGeometry Debug] File saved to server:', response.data);
						
						// Show success message
						var message = 'File saved to: ' + (response.data.filepath || 'debug/') + response.data.filename;
						if ( response.data.size ) {
							message += ' (' + response.data.size + ')';
						}
						console.log('[JetGeometry Debug]', message);
						
						// Also download to user's computer
						var blob = new Blob([jsonString], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'incident-debug-' + new Date().toISOString().split('T')[0] + '.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
						
						$button.prop('disabled', false).text(originalText);
					} else {
						console.error('[JetGeometry Debug] Error saving file:', response.data.message);
						alert('Error saving file to server: ' + (response.data.message || 'Unknown error') + '\n\nFile will still be downloaded to your computer.');
						
						// Still download to user's computer even if server save failed
						var blob = new Blob([jsonString], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'incident-debug-' + new Date().toISOString().split('T')[0] + '.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
						
						$button.prop('disabled', false).text(originalText);
					}
				},
				error: function(xhr, status, error) {
					console.error('[JetGeometry Debug] AJAX error saving file:', error);
					alert('Error saving file to server: ' + error + '\n\nFile will still be downloaded to your computer.');
					
					// Still download to user's computer even if server save failed
					var blob = new Blob([jsonString], { type: 'application/json' });
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'incident-debug-' + new Date().toISOString().split('T')[0] + '.json';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
					
					$button.prop('disabled', false).text(originalText);
				}
			});
		});

		// Country filter
		$(document).on('change', '#jet-debug-country-filter', function() {
			var selectedCountry = $(this).val();
			self.applyCountryFilter(selectedCountry);
		});

		// Helper function to get filtered posts
		this.getFilteredPosts = function() {
			if ( !window.JetGeometrySettingsDebugPosts || window.JetGeometrySettingsDebugPosts.length === 0 ) {
				return [];
			}

			var selectedCountry = $('#jet-debug-country-filter').val();
			if ( !selectedCountry ) {
				return window.JetGeometrySettingsDebugPosts;
			}

			return window.JetGeometrySettingsDebugPosts.filter(function(post) {
				return post.country && post.country.indexOf(selectedCountry) !== -1;
			});
		};

		// Apply country filter
		this.applyCountryFilter = function(country) {
			if ( !window.JetGeometrySettingsDebugPosts || window.JetGeometrySettingsDebugPosts.length === 0 ) {
				return;
			}

			var filteredPosts = country ? 
				window.JetGeometrySettingsDebugPosts.filter(function(post) {
					return post.country && post.country.indexOf(country) !== -1;
				}) : 
				window.JetGeometrySettingsDebugPosts;

			// Update filter count
			var $filterCount = $('#jet-debug-filter-count');
			if ( country ) {
				$filterCount.text('Showing ' + filteredPosts.length + ' of ' + window.JetGeometrySettingsDebugPosts.length + ' posts');
			} else {
				$filterCount.text('');
			}

			// Display filtered results
			JetGeometrySettings.displayDebugResults(filteredPosts, 1);
		};

		// Pagination
		$(document).on('click', '#jet-debug-pagination a', function(e) {
			e.preventDefault();
			if ( window.JetGeometrySettingsDebugAnalyzing ) {
				return;
			}
			var page = $(this).data('page');
			if ( page ) {
				currentPage = page;
				// Use cached posts if available
				if ( window.JetGeometrySettingsDebugPosts && window.JetGeometrySettingsDebugPosts.length > 0 ) {
					// Apply filter if active
					var selectedCountry = $('#jet-debug-country-filter').val();
					var postsToShow = selectedCountry ? 
						window.JetGeometrySettingsDebugPosts.filter(function(post) {
							return post.country && post.country.indexOf(selectedCountry) !== -1;
						}) : 
						window.JetGeometrySettingsDebugPosts;
					JetGeometrySettings.displayDebugResults(postsToShow, page);
				} else {
					self.loadDebugData(page, false);
				}
			}
		});
	};

	JetGeometrySettings.startDebugAnalysis = function() {
		var $startBtn = $('#jet-debug-start');
		var $refreshBtn = $('#jet-debug-refresh');
		var $downloadBtn = $('#jet-debug-download');
		var $spinner = $('#jet-debug-spinner');
		var $progress = $('#jet-debug-progress');
		var $progressFill = $('#jet-debug-progress-fill');
		var $progressText = $('#jet-debug-progress-text');
		var $progressStatus = $('#jet-debug-progress-status');
		var $table = $('#jet-debug-table tbody');
		var $pagination = $('#jet-debug-pagination');

		// Set analyzing flag
		window.JetGeometrySettingsDebugAnalyzing = true;

		$startBtn.prop('disabled', true).hide();
		$refreshBtn.hide();
		$downloadBtn.hide();
		$('#jet-debug-filters').hide();
		$('#jet-debug-country-filter').val('');
		$spinner.addClass('is-active').show();
		$progress.show();
		$progressFill.css('width', '0%');
		$progressText.text('0%');
		$progressStatus.text('Initializing...');
		$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px;"><span class="spinner is-active"></span> Starting analysis...</td></tr>');
		$pagination.html('');

		// First, get total count
		$.ajax({
			url: JetGeometryAdminSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jet_geometry_get_debug_list',
				nonce: JetGeometryAdminSettings.ajaxNonce,
				per_page: 1,
				page: 1
			},
			success: function(response) {
				console.log('[JetGeometry Debug] Initial response:', response);
				
				if ( !response ) {
					$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: No response from server</td></tr>');
					$startBtn.prop('disabled', false).show();
					$refreshBtn.show();
					$spinner.removeClass('is-active').hide();
					$progress.hide();
					window.JetGeometrySettingsDebugAnalyzing = false;
					return;
				}

				if ( !response.success ) {
					var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
					console.error('[JetGeometry Debug] Error response:', response);
					$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: ' + errorMsg + '</td></tr>');
					$startBtn.prop('disabled', false).show();
					$refreshBtn.show();
					$('#jet-debug-download').hide();
					$spinner.removeClass('is-active').hide();
					$progress.hide();
					window.JetGeometrySettingsDebugAnalyzing = false;
					return;
				}

				if ( response.success && response.data && typeof response.data.total !== 'undefined' ) {
					var total = parseInt(response.data.total, 10);
					if ( isNaN(total) || total < 0 ) {
						console.error('[JetGeometry Debug] Invalid total:', response.data.total);
						$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: Invalid total count: ' + response.data.total + '</td></tr>');
						$startBtn.prop('disabled', false).show();
						$refreshBtn.show();
						$('#jet-debug-download').hide();
						$spinner.removeClass('is-active').hide();
						$progress.hide();
						window.JetGeometrySettingsDebugAnalyzing = false;
						return;
					}
					
					var totalPages = Math.ceil(total / 100);
					var currentPage = 1;
					var allPosts = [];

					$progressStatus.text('Analyzing posts: 0 / ' + total);

					// Load all pages sequentially
					function loadPage(page) {
						if ( page > totalPages ) {
							// All pages loaded, display results
							$progressFill.css('width', '100%');
							$progressText.text('100%');
							$progressStatus.text('Analysis complete: ' + total + ' / ' + total);
							
							setTimeout(function() {
								JetGeometrySettings.displayDebugResults(allPosts, 1);
								$startBtn.prop('disabled', false).show();
								$refreshBtn.show();
								$('#jet-debug-download').show();
								$('#jet-debug-filters').show();
								$spinner.removeClass('is-active').hide();
								$progress.hide();
								window.JetGeometrySettingsDebugAnalyzing = false;
							}, 500);
							return;
						}

						var processed = Math.min((page - 1) * 100, total);
						var percent = Math.min(Math.round((processed / total) * 100), 100);
						$progressFill.css('width', percent + '%');
						$progressText.text(percent + '%');
						$progressStatus.text('Analyzing posts: ' + processed + ' / ' + total);

						$.ajax({
							url: JetGeometryAdminSettings.ajaxUrl,
							type: 'POST',
							data: {
								action: 'jet_geometry_get_debug_list',
								nonce: JetGeometryAdminSettings.ajaxNonce,
								per_page: 100,
								page: page
							},
							success: function(response) {
								if ( response.success && response.data && response.data.posts ) {
									allPosts = allPosts.concat(response.data.posts);
									// Load next page
									setTimeout(function() {
										loadPage(page + 1);
									}, 50); // Small delay to prevent overwhelming the server
								} else {
									// Error loading page
									$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error loading page ' + page + '</td></tr>');
									$startBtn.prop('disabled', false).show();
									$refreshBtn.show();
									$('#jet-debug-download').hide();
									$spinner.removeClass('is-active').hide();
									$progress.hide();
									window.JetGeometrySettingsDebugAnalyzing = false;
								}
							},
							error: function(xhr, status, error) {
								$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error loading page ' + page + ': ' + error + '</td></tr>');
								$startBtn.prop('disabled', false).show();
								$refreshBtn.show();
								$('#jet-debug-download').hide();
								$spinner.removeClass('is-active').hide();
								$progress.hide();
								window.JetGeometrySettingsDebugAnalyzing = false;
							}
						});
					}

					// Start loading pages
					loadPage(1);
				} else {
					console.error('[JetGeometry Debug] Invalid response structure:', response);
					var errorMsg = 'Could not get total count';
					if ( response.data && response.data.message ) {
						errorMsg = response.data.message;
					} else if ( !response.data ) {
						errorMsg = 'Response data is missing';
					} else if ( typeof response.data.total === 'undefined' ) {
						errorMsg = 'Total count is missing in response. Response keys: ' + Object.keys(response.data).join(', ');
					}
					$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: ' + errorMsg + '</td></tr>');
					$startBtn.prop('disabled', false).show();
					$refreshBtn.show();
					$('#jet-debug-download').hide();
					$spinner.removeClass('is-active').hide();
					$progress.hide();
					window.JetGeometrySettingsDebugAnalyzing = false;
				}
			},
			error: function(xhr, status, error) {
				console.error('[JetGeometry Debug] AJAX error:', {
					status: status,
					error: error,
					statusCode: xhr.status,
					responseText: xhr.responseText
				});
				var errorMsg = error || 'Unknown error';
				if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					errorMsg = xhr.responseJSON.data.message;
				} else if ( xhr.status === 0 ) {
					errorMsg = 'Connection error - check if WordPress is running';
				} else if ( xhr.status === 403 ) {
					errorMsg = 'Permission denied - check nonce';
				} else if ( xhr.status === 500 ) {
					errorMsg = 'Server error - check PHP error logs';
				}
				$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: ' + errorMsg + '</td></tr>');
				$startBtn.prop('disabled', false).show();
				$refreshBtn.show();
				$('#jet-debug-download').hide();
				$spinner.removeClass('is-active').hide();
				$progress.hide();
				window.JetGeometrySettingsDebugAnalyzing = false;
			}
		});
	};

	JetGeometrySettings.displayDebugResults = function(posts, page) {
		var $table = $('#jet-debug-table tbody');
		var $pagination = $('#jet-debug-pagination');
		var perPage = 100;
		var totalPages = Math.ceil(posts.length / perPage);
		var startIndex = (page - 1) * perPage;
		var endIndex = Math.min(startIndex + perPage, posts.length);
		var pagePosts = posts.slice(startIndex, endIndex);

		// Update filter count if filter is active
		var selectedCountry = $('#jet-debug-country-filter').val();
		var $filterCount = $('#jet-debug-filter-count');
		if ( selectedCountry && window.JetGeometrySettingsDebugPosts ) {
			$filterCount.text('Showing ' + posts.length + ' of ' + window.JetGeometrySettingsDebugPosts.length + ' posts');
		} else {
			$filterCount.text('');
		}

		var html = '';

		if ( pagePosts.length === 0 ) {
			html = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No incidents found.</td></tr>';
		} else {
			pagePosts.forEach(function(post) {
				var statusClass = post.status === 'publish' ? 'status-publish' : 'status-' + post.status;
				var validIcon = post.has_valid_geometry ? '✓' : '✗';
				var validColor = post.has_valid_geometry ? '#00a32a' : '#d63638';
				var issueColor = post.address_issue_code === 'none' ? '#666' : '#d63638';

				html += '<tr>';
				html += '<td><a href="' + JetGeometryAdminSettings.adminUrl + 'post.php?post=' + post.id + '&action=edit" target="_blank">' + post.id + '</a></td>';
				html += '<td><strong>' + (post.title || '(no title)') + '</strong></td>';
				html += '<td><span class="' + statusClass + '">' + post.status + '</span></td>';
				html += '<td>' + (post.country || '—') + '</td>';
				html += '<td style="color: ' + issueColor + ';">' + (post.address_issue || '—') + '</td>';
				html += '<td>' + (post.geometry_type || '—') + '</td>';
				html += '<td style="color: ' + validColor + '; font-weight: bold;">' + validIcon + '</td>';
				html += '<td style="font-size: 12px; color: #666;">' + (post.additional_info || '—') + '</td>';
				html += '</tr>';
			});
		}

		$table.html(html);

		// Pagination
		if ( totalPages > 1 ) {
			var paginationHtml = '<div class="tablenav-pages">';
			paginationHtml += '<span class="displaying-num">' + posts.length + ' items</span>';
			paginationHtml += '<span class="pagination-links">';

			// Previous
			if ( page > 1 ) {
				paginationHtml += '<a class="button" href="#" data-page="' + (page - 1) + '">‹</a>';
			} else {
				paginationHtml += '<span class="button disabled">‹</span>';
			}

			// Page numbers
			for ( var i = 1; i <= totalPages; i++ ) {
				if ( i === page ) {
					paginationHtml += '<span class="button button-primary">' + i + '</span>';
				} else if ( Math.abs(i - page) <= 2 || i === 1 || i === totalPages ) {
					paginationHtml += '<a class="button" href="#" data-page="' + i + '">' + i + '</a>';
				} else if ( Math.abs(i - page) === 3 ) {
					paginationHtml += '<span class="button disabled">…</span>';
				}
			}

			// Next
			if ( page < totalPages ) {
				paginationHtml += '<a class="button" href="#" data-page="' + (page + 1) + '">›</a>';
			} else {
				paginationHtml += '<span class="button disabled">›</span>';
			}

			paginationHtml += '</span></div>';
			$pagination.html(paginationHtml);
		} else {
			$pagination.html('');
		}

		// Store posts in global variable for pagination
		window.JetGeometrySettingsDebugPosts = posts;
	};

	JetGeometrySettings.loadDebugData = function(page, showSpinner) {
		showSpinner = (typeof showSpinner === 'undefined') ? true : showSpinner;
		
		var $spinner = $('#jet-debug-spinner');
		var $table = $('#jet-debug-table tbody');
		var $pagination = $('#jet-debug-pagination');

		if ( showSpinner ) {
			$spinner.addClass('is-active').show();
		}
		$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px;"><span class="spinner is-active"></span> Loading...</td></tr>');

		// If we have cached posts, use them
		if ( window.JetGeometrySettingsDebugPosts && window.JetGeometrySettingsDebugPosts.length > 0 ) {
			JetGeometrySettings.displayDebugResults(window.JetGeometrySettingsDebugPosts, page || 1);
			if ( showSpinner ) {
				$spinner.removeClass('is-active').hide();
			}
			return;
		}

		// Otherwise, load from server
		$.ajax({
			url: JetGeometryAdminSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'jet_geometry_get_debug_list',
				nonce: JetGeometryAdminSettings.ajaxNonce,
				per_page: 100,
				page: page || 1
			},
			success: function(response) {
				if ( showSpinner ) {
					$spinner.removeClass('is-active').hide();
				}

				if ( response.success && response.data && response.data.posts ) {
					var posts = response.data.posts;
					var html = '';

					if ( posts.length === 0 ) {
						html = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No incidents found.</td></tr>';
					} else {
						posts.forEach(function(post) {
							var statusClass = post.status === 'publish' ? 'status-publish' : 'status-' + post.status;
							var validIcon = post.has_valid_geometry ? '✓' : '✗';
							var validColor = post.has_valid_geometry ? '#00a32a' : '#d63638';
							var issueColor = post.address_issue_code === 'none' ? '#666' : '#d63638';

							html += '<tr>';
							html += '<td><a href="' + JetGeometryAdminSettings.adminUrl + 'post.php?post=' + post.id + '&action=edit" target="_blank">' + post.id + '</a></td>';
							html += '<td><strong>' + (post.title || '(no title)') + '</strong></td>';
							html += '<td><span class="' + statusClass + '">' + post.status + '</span></td>';
							html += '<td>' + (post.country || '—') + '</td>';
							html += '<td style="color: ' + issueColor + ';">' + (post.address_issue || '—') + '</td>';
							html += '<td>' + (post.geometry_type || '—') + '</td>';
							html += '<td style="color: ' + validColor + '; font-weight: bold;">' + validIcon + '</td>';
							html += '<td style="font-size: 12px; color: #666;">' + (post.additional_info || '—') + '</td>';
							html += '</tr>';
						});
					}

					$table.html(html);

					// Pagination
					if ( response.data.total_pages > 1 ) {
						var paginationHtml = '<div class="tablenav-pages">';
						paginationHtml += '<span class="displaying-num">' + response.data.total + ' items</span>';
						paginationHtml += '<span class="pagination-links">';

						// Previous
						if ( response.data.page > 1 ) {
							paginationHtml += '<a class="button" href="#" data-page="' + (response.data.page - 1) + '">‹</a>';
						} else {
							paginationHtml += '<span class="button disabled">‹</span>';
						}

						// Page numbers
						for ( var i = 1; i <= response.data.total_pages; i++ ) {
							if ( i === response.data.page ) {
								paginationHtml += '<span class="button button-primary">' + i + '</span>';
							} else if ( Math.abs(i - response.data.page) <= 2 || i === 1 || i === response.data.total_pages ) {
								paginationHtml += '<a class="button" href="#" data-page="' + i + '">' + i + '</a>';
							} else if ( Math.abs(i - response.data.page) === 3 ) {
								paginationHtml += '<span class="button disabled">…</span>';
							}
						}

						// Next
						if ( response.data.page < response.data.total_pages ) {
							paginationHtml += '<a class="button" href="#" data-page="' + (response.data.page + 1) + '">›</a>';
						} else {
							paginationHtml += '<span class="button disabled">›</span>';
						}

						paginationHtml += '</span></div>';
						$pagination.html(paginationHtml);
					} else {
						$pagination.html('');
					}
				} else {
					$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error loading debug data.</td></tr>');
				}
			},
			error: function(xhr, status, error) {
				if ( showSpinner ) {
					$spinner.removeClass('is-active').hide();
				}
				$table.html('<tr><td colspan="8" style="text-align: center; padding: 20px; color: #d63638;">Error: ' + error + '</td></tr>');
			}
		});
	};

	JetGeometrySettings.bindCacheMode = function() {
		var self = this;
		
		// Handle cache mode change
		$('#jet_geometry_cache_mode').on('change', function() {
			var mode = $(this).val();
			if (mode === 'json') {
				$('#regenerate-cache').closest('div').show();
			} else {
				$('#regenerate-cache').closest('div').hide();
			}
		});
		
		// Handle regenerate cache button
		$('#regenerate-cache').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $spinner = $('#cache-regenerate-spinner');
			var $status = $button.siblings('span').not('.spinner');
			
			$button.prop('disabled', true);
			$spinner.show();
			
			$.ajax({
				url: JetGeometryAdminSettings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'jet_geometry_regenerate_cache',
					nonce: JetGeometryAdminSettings.ajaxNonce
				},
				success: function(response) {
					if (response.success) {
						var message = response.data.message || 'Cache regenerated successfully';
						if (response.data.cache_info && response.data.cache_info.markers_count) {
							message += ' - ' + response.data.cache_info.markers_count + ' ' + 'incidentów';
						}
						$status.html('<strong>' + message + '</strong>').css('color', '#46b450');
						// Reload page after 2 seconds to show updated cache status and file links
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						$status.text(response.data.message || 'Error').css('color', '#dc3232');
						$button.prop('disabled', false);
						$spinner.hide();
					}
				},
				error: function(xhr, status, error) {
					$status.text('AJAX error: ' + error).css('color', '#dc3232');
					$button.prop('disabled', false);
					$spinner.hide();
				}
			});
		});
		
		// Show/hide regenerate button based on current mode
		var currentMode = $('#jet_geometry_cache_mode').val();
		if (currentMode === 'json') {
			$('#regenerate-cache').closest('div').show();
		}
	};

	// Initialize
	$(document).ready(function() {
		console.log('JetGeometrySettings: DOM ready, initializing...');
		if ( $('.jet-geometry-settings').length ) {
			console.log('JetGeometrySettings: Found settings page, calling init()');
			JetGeometrySettings.init();
		} else {
			console.warn('JetGeometrySettings: Settings page not found');
		}
	});
	
	// Also try immediate initialization if DOM is already ready
	if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
		setTimeout(function() {
			if ( jQuery('.jet-geometry-settings').length ) {
				console.log('JetGeometrySettings: DOM already ready, initializing...');
				JetGeometrySettings.init();
			}
		}, 50);
	}

})(jQuery);




