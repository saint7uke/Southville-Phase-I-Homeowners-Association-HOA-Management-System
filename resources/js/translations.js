import en from '../../lang/en.json';
import fil from '../../lang/fil.json';

export const dictionaries = { en, fil };
const reverse = Object.fromEntries(Object.entries(fil).map(([key, value]) => [value, key]));
export const translationKey = (value) => Object.hasOwn(en, value) ? value : (reverse[value] ?? value);
export function translate(key, locale = 'en', parameters = {}) {
    const canonical = translationKey(key);
    let message = dictionaries[locale]?.[canonical] ?? en[canonical] ?? canonical;
    for (const [name, value] of Object.entries(parameters)) {
        message = message.replaceAll(`:${name}`, String(value));
    }
    return message;
}
