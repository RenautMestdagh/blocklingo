/**
 * Editor state: the language being edited.
 */
import { createReduxStore, register } from '@wordpress/data';

import { settings } from './settings';

export const STORE_NAME = 'blocklingo/editor';

const DEFAULT_STATE = { language: settings.defaultLanguage };

export const store = createReduxStore( STORE_NAME, {
	reducer( state = DEFAULT_STATE, action ) {
		if ( action.type === 'SET_LANGUAGE' ) {
			return { ...state, language: action.language };
		}
		return state;
	},
	actions: {
		setLanguage( language ) {
			return { type: 'SET_LANGUAGE', language };
		},
	},
	selectors: {
		getLanguage( state ) {
			return state.language;
		},
		isTranslating( state ) {
			return state.language !== settings.defaultLanguage;
		},
	},
} );

register( store );
