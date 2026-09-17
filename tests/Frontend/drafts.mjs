import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { transpileModule, ModuleKind } from 'typescript';

// Exercise the real storage module without a browser or another test dependency.
const entries = new Map();
globalThis.localStorage = {
    getItem: (key) => entries.get(key) ?? null,
    setItem: (key, value) => entries.set(key, value),
    removeItem: (key) => entries.delete(key),
};
const form = { dataset: { draftKey: 'new', restoreDraft: '1', savedAt: '0' } };
globalThis.document = { body: { dataset: { userId: '1' } }, querySelector: () => form };
const source = readFileSync(new URL('../../resources/js/core/storage.ts', import.meta.url), 'utf8');
const { outputText } = transpileModule(source, { compilerOptions: { module: ModuleKind.ESNext } });
const storage = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`);

storage.updatePublishDraft({ fields: { title: 'A sketch' } });
storage.updatePublishDraft({ contentHtml: '<p>One small step</p>' });
assert.equal(storage.recoverPublishDraft(form).fields.title, 'A sketch');
assert.equal(storage.getPublishDraft().contentHtml, '<p>One small step</p>');
form.dataset.restoreDraft = '0';
assert.equal(storage.recoverPublishDraft(form), null, 'Validation input takes priority over recovery');
form.dataset.restoreDraft = '1';
form.dataset.savedAt = String(Date.now() + 10000);
assert.equal(storage.recoverPublishDraft(form), null, 'A newer server version takes priority');
form.dataset.savedAt = '0';
form.dataset.draftKey = 'journal-1-new';
assert.equal(storage.getPublishDraft(), null, 'Journal and project drafts stay separate');
storage.updatePublishDraft({ fields: { title: 'An update' } });
document.body.dataset.userId = '2';
assert.equal(storage.getPublishDraft(), null, 'Accounts never share drafts');
document.body.dataset.userId = '1';
storage.clearPublishDraft('new');
assert.equal(storage.getPublishDraft().fields.title, 'An update', 'Clearing a saved post preserves its journal draft');
localStorage.getItem = () => { throw new Error('Storage disabled'); };
localStorage.setItem = localStorage.removeItem = localStorage.getItem;
assert.equal(storage.getPublishDraft(), null);
assert.doesNotThrow(() => storage.updatePublishDraft({ fields: {} }));
assert.doesNotThrow(() => storage.clearPublishDraft());
console.log('Draft recovery checks passed.');
