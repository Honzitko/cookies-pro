#!/usr/bin/env node
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
	constructor(name) {
		this.name = name;
		this.hidden = false;
		this.inert = false;
		this.disabled = false;
		this.isConnected = true;
		this.attributes = {};
		this.listeners = {};
	}

	addEventListener(type, listener) { this.listeners[type] = listener; }
	getAttribute(name) { return this.attributes[name] || null; }
	setAttribute(name, value) { this.attributes[name] = value; }
	contains(element) { return element === this || this.children && this.children.includes(element); }
	focus() { document.activeElement = this; }
}

Element.prototype.inert = false;

const document = {
	cookie: '',
	readyState: 'complete',
	activeElement: null,
	listeners: {},
	documentElement: { style: { overflow: 'visible' } },
	addEventListener(type, listener) { this.listeners[type] = listener; },
	getElementById(id) { return this.elements[id] || null; },
	elements: {}
};

const opener = new Element('opener');
const background = new Element('background');
const prefs = new Element('prefs');
prefs.hidden = true;
const close = new Element('close');
const functional = new Element('functional');
const analytics = new Element('analytics');
const marketing = new Element('marketing');
const reject = new Element('reject');
const save = new Element('save');
const accept = new Element('accept');
const focusable = [close, functional, analytics, marketing, reject, save, accept];
const inputs = { functional, analytics, marketing };
prefs.children = focusable;
prefs.querySelector = selector => {
	const category = selector.match(/data-cat="([^"]+)"/);
	return category ? inputs[category[1]] : (selector === 'input[data-cat]' ? functional : null);
};
prefs.querySelectorAll = selector => selector.includes('a[href]') ? focusable : [];
const banner = new Element('banner');
banner.querySelectorAll = () => [];
prefs.querySelectorAllData = () => [];
const floatBtn = new Element('float');
document.elements = { 'fc-banner': banner, 'fc-prefs': prefs, 'fc-float': floatBtn };
document.body = { children: [background, prefs] };

global.document = document;
global.window = { FUTURI_COOKIES: {} };
global.HTMLElement = Element;
global.location = { protocol: 'https:' };
global.URLSearchParams = URLSearchParams;
global.fetch = () => Promise.resolve();

vm.runInThisContext(fs.readFileSync('assets/banner.js', 'utf8'), { filename: 'assets/banner.js' });

function keydown(key, shiftKey) {
	let prevented = false;
	document.listeners.keydown({ key, shiftKey: !!shiftKey, preventDefault() { prevented = true; } });
	return prevented;
}

document.activeElement = opener;
window.futuriCookies.open();
assert.equal(document.activeElement, functional, 'Opening preferences focuses the first modal control.');
assert.equal(background.inert, true, 'Background content becomes inert while the modal is open.');
assert.equal(document.documentElement.style.overflow, 'hidden', 'Opening the modal locks document scrolling.');

document.activeElement = accept;
assert.equal(keydown('Tab'), true, 'Tab at the final control is intercepted.');
assert.equal(document.activeElement, close, 'Tab cycles from the final modal control to the first.');

document.activeElement = close;
assert.equal(keydown('Tab', true), true, 'Shift+Tab at the first control is intercepted.');
assert.equal(document.activeElement, accept, 'Shift+Tab cycles from the first modal control to the final.');

document.activeElement = background;
assert.equal(keydown('Tab'), true, 'Tab from outside the modal is intercepted.');
assert.equal(document.activeElement, close, 'The focus trap returns focus to the modal.');

keydown('Escape');
assert.equal(prefs.hidden, true, 'Escape closes the modal.');
assert.equal(document.activeElement, opener, 'Closing returns focus to the opening control.');
assert.equal(background.inert, false, 'Closing restores background interactivity.');
assert.equal(document.documentElement.style.overflow, 'visible', 'Closing restores the previous scroll state.');

console.log('Modal focus trapping, Escape handling, focus restoration, and scroll restoration passed.');
