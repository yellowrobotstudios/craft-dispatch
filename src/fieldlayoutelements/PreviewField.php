<?php

namespace yellowrobot\craftdispatch\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldLayoutElement;
use craft\helpers\Html;
use craft\helpers\Json;
use yellowrobot\craftdispatch\CraftDispatch;
use yellowrobot\craftdispatch\elements\EmailTemplate;

class PreviewField extends FieldLayoutElement
{
    public function selectorHtml(): string
    {
        return 'Email Preview';
    }

    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof EmailTemplate || !$element->handle) {
            return null;
        }

        $hook = CraftDispatch::$plugin->hook->getHookByHandle($element->handle);
        if (!$hook || !$hook->previewElementType) {
            return null;
        }

        $previewElementType = $hook->previewElementType;
        $previewCriteria = $hook->previewCriteria;
        $previewSources = $hook->previewSources;

        // Build the HTML
        $html = '';

        // Hidden inputs
        $html .= Html::hiddenInput('previewElementType', $previewElementType, ['id' => 'preview-element-type']);
        $html .= Html::hiddenInput('previewElementId', '', ['id' => 'preview-element-id']);

        // Separator
        $html .= Html::tag('hr');

        // Preview toolbar
        $toolbarLeft = Html::tag('h2', 'Preview', ['style' => 'margin:0;']);
        $toolbarLeft .= Html::tag('div', '', ['id' => 'preview-element-select', 'style' => 'display:inline-flex; align-items:center; gap:8px;']);
        $toolbarLeft .= Html::button('Render', [
            'type' => 'button',
            'id' => 'preview-render-btn',
            'class' => 'btn submit small',
            'disabled' => true,
        ]);
        $toolbarLeftWrap = Html::tag('div', $toolbarLeft, ['style' => 'display:flex; align-items:center; gap:12px;']);

        $modeToggles = Html::button('HTML', [
            'type' => 'button',
            'class' => 'btn active',
            'data-preview-mode' => 'html',
        ]);
        $modeToggles .= Html::button('Text', [
            'type' => 'button',
            'class' => 'btn',
            'data-preview-mode' => 'text',
        ]);
        $modeTogglesWrap = Html::tag('div', $modeToggles, [
            'class' => 'btngroup small',
            'id' => 'preview-mode-toggles',
            'style' => 'display:none;',
        ]);

        $html .= Html::tag('div', $toolbarLeftWrap . $modeTogglesWrap, [
            'style' => 'display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;',
        ]);

        // Rendered subject
        $subjectLabel = Html::tag('div', 'Subject', [
            'style' => 'font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:4px;',
        ]);
        $subjectText = Html::tag('div', '', [
            'id' => 'preview-subject-text',
            'style' => 'font-size:16px; font-weight:600; color:#1f2937;',
        ]);
        $html .= Html::tag('div', $subjectLabel . $subjectText, [
            'id' => 'preview-subject',
            'style' => 'display:none; margin-bottom:16px; padding:12px 16px; background:#f3f4f6; border:1px solid #e3e5e8; border-radius:6px;',
        ]);

        // Error
        $html .= Html::tag('div', '', [
            'id' => 'preview-error',
            'class' => 'error',
            'style' => 'display:none; margin-bottom:16px;',
        ]);

        // Preview frame container
        $iframe = Html::tag('iframe', '', [
            'id' => 'preview-iframe',
            'style' => 'width:100%; border:1px solid #e3e5e8; border-radius:4px; background:#fff; min-height:400px;',
            'sandbox' => 'allow-same-origin',
        ]);
        $resizeHandleInner = Html::tag('div', '', [
            'style' => 'width:4px; height:48px; background:#cbd5e1; border-radius:2px;',
        ]);
        $resizeHandle = Html::tag('div', $resizeHandleInner, [
            'id' => 'preview-resize-handle',
            'style' => 'position:absolute; top:0; right:-12px; width:24px; height:100%; cursor:col-resize; display:flex; align-items:center; justify-content:center;',
        ]);
        $iframeWrap = Html::tag('div', $iframe . $resizeHandle, [
            'id' => 'preview-iframe-wrap',
            'style' => 'position:relative; margin:0 auto; width:100%; max-width:100%;',
        ]);
        $widthLabel = Html::tag('div', '', [
            'id' => 'preview-width-label',
            'style' => 'text-align:center; margin-top:8px; font-size:12px; color:#9ca3af; display:none;',
        ]);
        $html .= Html::tag('div', $iframeWrap . $widthLabel, [
            'id' => 'preview-frame-container',
            'style' => 'display:none; border:1px solid #e3e5e8; border-radius:8px; background:#f9fafb; padding:24px;',
        ]);

        // Text container
        $html .= Html::tag('div', '', [
            'id' => 'preview-text-container',
            'style' => 'display:none; border:1px solid #e3e5e8; border-radius:8px; padding:24px; background:#fff; white-space:pre-wrap; font-family:monospace; font-size:13px; min-height:200px;',
        ]);

        // Empty state
        $emptyMessage = Html::tag('p', 'Select an element and click Render to preview this email.', [
            'style' => 'margin:0; font-size:15px;',
        ]);
        $html .= Html::tag('div', $emptyMessage, [
            'id' => 'preview-empty',
            'style' => 'border:1px dashed #d1d5db; border-radius:6px; padding:24px; text-align:center; color:#9ca3af;',
        ]);

        // Register JavaScript
        $jsElementType = Json::encode($previewElementType);
        $jsCriteria = Json::encode($previewCriteria);
        $jsSources = Json::encode($previewSources);
        $jsHandle = Json::encode($element->handle);

        $js = <<<JS
(function() {
    var PREVIEW_ELEMENT_TYPE = {$jsElementType};
    var PREVIEW_CRITERIA = {$jsCriteria};
    var PREVIEW_SOURCES = {$jsSources};
    var PREVIEW_HANDLE = {$jsHandle};

    var \$selectContainer = $('#preview-element-select');
    var renderBtn = document.getElementById('preview-render-btn');
    var modeToggles = document.getElementById('preview-mode-toggles');
    var selectedElementId = null;

    function showSelectButton() {
        \$selectContainer.html('<button type="button" class="btn small" id="select-element-btn">Select Element</button>');
        document.getElementById('select-element-btn').addEventListener('click', openSelector);
    }

    function openSelector() {
        var \$trigger = \$selectContainer.find('button').first();
        Craft.createElementSelectorModal(PREVIEW_ELEMENT_TYPE, {
            multiSelect: false,
            sources: PREVIEW_SOURCES,
            criteria: PREVIEW_CRITERIA,
            \$triggerElement: \$trigger,
            onSelect: function(elements) {
                if (elements.length) {
                    selectedElementId = elements[0].id;
                    document.getElementById('preview-element-id').value = selectedElementId;
                    \$selectContainer.html(
                        '<span class="status green" style="margin-right:4px;"></span>' +
                        '<span style="font-weight:500;">' + Craft.escapeHtml(elements[0].label) + '</span>' +
                        '&nbsp;<button type="button" class="btn small" id="change-element-btn">Change</button>'
                    );
                    document.getElementById('change-element-btn').addEventListener('click', openSelector);
                    renderBtn.disabled = false;
                }
            },
        });
    }

    showSelectButton();

    renderBtn.addEventListener('click', function() {
        if (!selectedElementId) return;
        renderBtn.disabled = true;
        renderBtn.textContent = 'Rendering\u2026';

        var data = {
            handle: (document.getElementById('handle') || {}).value || PREVIEW_HANDLE,
            subject: document.getElementById('subject').value,
            htmlBody: document.getElementById('htmlBody').value,
            textBody: document.getElementById('textBody').value,
            previewElementId: selectedElementId,
            previewElementType: document.getElementById('preview-element-type').value,
        };

        fetch(Craft.getActionUrl('craft-dispatch/templates/preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': Craft.csrfTokenValue,
            },
            body: JSON.stringify(data),
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            renderBtn.disabled = false;
            renderBtn.textContent = 'Render';

            var errorEl = document.getElementById('preview-error');
            var emptyEl = document.getElementById('preview-empty');
            var subjectEl = document.getElementById('preview-subject');
            var frameContainer = document.getElementById('preview-frame-container');
            var textContainer = document.getElementById('preview-text-container');

            if (result.success) {
                errorEl.style.display = 'none';
                emptyEl.style.display = 'none';
                subjectEl.style.display = 'block';
                document.getElementById('preview-subject-text').textContent = result.subject;

                var iframe = document.getElementById('preview-iframe');
                iframe.srcdoc = result.html;
                iframe.onload = function() {
                    try {
                        var h = iframe.contentDocument.documentElement.scrollHeight;
                        iframe.style.height = Math.max(h, 200) + 'px';
                    } catch(e) {}
                };

                textContainer.textContent = result.text;
                modeToggles.style.display = 'inline-flex';
                showPreviewMode(modeToggles.querySelector('.btn.active').getAttribute('data-preview-mode'));
            } else {
                emptyEl.style.display = 'none';
                subjectEl.style.display = 'none';
                frameContainer.style.display = 'none';
                textContainer.style.display = 'none';
                modeToggles.style.display = 'none';
                errorEl.textContent = result.error;
                errorEl.style.display = 'block';
            }
        })
        .catch(function(err) {
            renderBtn.disabled = false;
            renderBtn.textContent = 'Render';
            document.getElementById('preview-error').textContent = 'Request failed: ' + err.message;
            document.getElementById('preview-error').style.display = 'block';
        });
    });

    // Draggable resize handle
    (function() {
        var handle = document.getElementById('preview-resize-handle');
        var wrap = document.getElementById('preview-iframe-wrap');
        var container = document.getElementById('preview-frame-container');
        var label = document.getElementById('preview-width-label');
        var iframe = document.getElementById('preview-iframe');
        var dragging = false;

        if (!handle) return;

        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            dragging = true;
            label.style.display = 'block';
            iframe.style.pointerEvents = 'none';
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            var containerRect = container.getBoundingClientRect();
            var containerCenter = containerRect.left + containerRect.width / 2;
            var halfWidth = Math.abs(e.clientX - containerCenter);
            var newWidth = Math.min(Math.max(halfWidth * 2, 280), containerRect.width - 48);
            wrap.style.width = Math.round(newWidth) + 'px';
            label.textContent = Math.round(newWidth) + 'px';
        });

        document.addEventListener('mouseup', function() {
            if (!dragging) return;
            dragging = false;
            iframe.style.pointerEvents = '';
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            setTimeout(function() { label.style.display = 'none'; }, 1500);
        });
    })();

    // Mode toggle
    var allPreviewPanes = {
        html: document.getElementById('preview-frame-container'),
        text: document.getElementById('preview-text-container'),
    };

    function showPreviewMode(mode) {
        Object.keys(allPreviewPanes).forEach(function(key) {
            if (allPreviewPanes[key]) {
                allPreviewPanes[key].style.display = key === mode ? 'block' : 'none';
            }
        });
        modeToggles.querySelectorAll('.btn').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-preview-mode') === mode);
        });
    }

    modeToggles.querySelectorAll('.btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            showPreviewMode(this.getAttribute('data-preview-mode'));
        });
    });
})();
JS;

        Craft::$app->getView()->registerJs($js);

        return Html::tag('div', $html, ['class' => 'field']);
    }
}
