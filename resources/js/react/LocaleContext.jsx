import React, { createContext, useContext, useEffect, useState } from 'react';
import { translate } from '../locale';

const LocaleContext = createContext({ locale: 'en', t: key => key });
export function LocaleProvider({ children }) {
    const [locale, setLocale] = useState(document.documentElement.lang || 'en');
    useEffect(() => { const handler = event => setLocale(event.detail.locale); window.addEventListener('hoa:locale', handler); return () => window.removeEventListener('hoa:locale', handler); }, []);
    return <LocaleContext.Provider value={{ locale, t: (key, parameters = {}) => translate(key, locale, parameters) }}>{children}</LocaleContext.Provider>;
}
export const useLocale = () => useContext(LocaleContext);
