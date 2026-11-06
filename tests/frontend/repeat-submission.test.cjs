const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('pending form navigation rejects repeats, and back navigation allows retry', () => {
    const handlers = {};
    const form = {
        dataset: {}, attributes: {},
        addEventListener(type, handler) { handlers[type] = handler; },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
    };
    const lifecycle = {};
    const context = {
        document: {
            addEventListener(type, handler) { lifecycle[type] = handler; },
            querySelectorAll(selector) { return selector === 'form[data-prevent-repeat]' ? [form] : []; },
        },
        window: { addEventListener(type, handler) { lifecycle[type] = handler; } },
    };
    const source = fs.readFileSync('resources/js/app.js', 'utf8').replace("import './bootstrap';", '');
    vm.runInNewContext(source, context);
    lifecycle.DOMContentLoaded();
    const event = () => ({ defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
    const cancelled = event();
    cancelled.preventDefault();
    handlers.submit(cancelled);
    const first = event();
    handlers.submit(first);
    assert.equal(first.defaultPrevented, false);
    assert.equal(form.attributes['aria-busy'], 'true');
    const repeat = event();
    handlers.submit(repeat);
    assert.equal(repeat.defaultPrevented, true);
    lifecycle.pageshow();
    const retry = event();
    handlers.submit(retry);
    assert.equal(retry.defaultPrevented, false);
});
