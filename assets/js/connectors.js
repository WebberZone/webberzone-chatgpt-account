/**
 * ChatGPT Account card for Settings → Connectors.
 *
 * Hand-written ES module (no build step). React, components and i18n come from the
 * wp-* classic scripts that the Connectors page already loads.
 */
import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const {
	createElement: el,
	createInterpolateElement,
	useEffect,
	useRef,
	useState,
} = window.wp.element;
const {
	Button,
	ExternalLink,
	Modal,
	Notice,
	Spinner,
	__experimentalHStack: HStack,
	__experimentalVStack: VStack,
	__experimentalText: Text,
} = window.wp.components;
const { __, sprintf } = window.wp.i18n;

const DOMAIN = 'webberzone-chatgpt-account';
const dataElement = document.getElementById( 'wp-script-module-data-wzcga-connectors' );
const config = dataElement ? JSON.parse( dataElement.textContent ) : {};

function call( action ) {
	return window
		.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( { action, _ajax_nonce: config.nonce } ),
		} )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			if ( ! result.success ) {
				throw new Error( result.data || __( 'Request failed.', DOMAIN ) );
			}
			return result.data;
		} );
}

function SecurityInstruction() {
	return el(
		Text,
		{ as: 'p' },
		createInterpolateElement(
			__( 'Before signing in, enable device code login for Codex in <a>ChatGPT → Settings → Security</a>.', DOMAIN ),
			{ a: el( ExternalLink, { href: config.securityUrl } ) }
		)
	);
}

function SignInModal( { onClose, onConnected } ) {
	const [ flow, setFlow ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();

	useEffect( () => {
		let active = true;
		setFlow( null );
		setError( null );

		const poll = ( interval ) => {
			timer.current = window.setTimeout( () => {
				call( 'wzcga_poll' )
					.then( ( status ) => {
						if ( ! active ) {
							return;
						}
						if ( 'connected' === status ) {
							onConnected();
							return;
						}
						poll( interval );
					} )
					.catch( ( e ) => active && setError( e.message ) );
			}, interval * 1000 );
		};

		call( 'wzcga_start' )
			.then( ( data ) => {
				if ( active ) {
					setFlow( data );
					poll( data.interval );
				}
			} )
			.catch( ( e ) => active && setError( e.message ) );

		return () => {
			active = false;
			window.clearTimeout( timer.current );
		};
	}, [ attempt ] );

	const close = () => {
		window.clearTimeout( timer.current );
		call( 'wzcga_cancel' ).catch( () => {} );
		onClose();
	};

	const copy = () => {
		window.navigator.clipboard
			.writeText( flow.user_code )
			.then( () => setCopied( true ) )
			.catch( () => {} );
	};

	let body;
	if ( error ) {
		body = el(
			VStack,
			{ spacing: 4 },
			el( Notice, { status: 'error', isDismissible: false }, error ),
			el( SecurityInstruction ),
			el(
				HStack,
				{ justify: 'flex-start' },
				el( Button, { variant: 'primary', onClick: () => setAttempt( attempt + 1 ) }, __( 'Try again', DOMAIN ) ),
				el( Button, { variant: 'tertiary', onClick: close }, __( 'Cancel', DOMAIN ) )
			)
		);
	} else if ( ! flow ) {
		body = el( HStack, { justify: 'flex-start' }, el( Spinner ), el( Text, null, __( 'Requesting a sign-in code…', DOMAIN ) ) );
	} else {
		body = el(
			VStack,
			{ spacing: 4 },
			el( SecurityInstruction ),
			el(
				Text,
				{ as: 'p' },
				createInterpolateElement( __( '1. Open <a>the ChatGPT device sign-in page</a> and sign in.', DOMAIN ), {
					a: el( ExternalLink, { href: flow.verification_url } ),
				} )
			),
			el( Text, { as: 'p' }, __( '2. Enter this code:', DOMAIN ) ),
			el(
				HStack,
				{ justify: 'flex-start', spacing: 3 },
				el(
					'code',
					{ style: { fontSize: '1.75em', padding: '0.4em 0.6em', letterSpacing: '0.1em', userSelect: 'all' } },
					flow.user_code
				),
				el( Button, { variant: 'secondary', size: 'compact', onClick: copy }, copied ? __( 'Copied', DOMAIN ) : __( 'Copy', DOMAIN ) )
			),
			el(
				HStack,
				{ justify: 'flex-start' },
				el( Spinner ),
				el( Text, { variant: 'muted' }, __( 'Waiting for you to approve the sign-in. The code expires in 15 minutes.', DOMAIN ) )
			)
		);
	}

	return el( Modal, { title: __( 'Sign in with ChatGPT', DOMAIN ), onRequestClose: close, size: 'medium' }, body );
}

function ChatGptAccountConnector( { name, description, logo } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const connected = !! config.connected;

	const disconnect = () => {
		setIsBusy( true );
		setError( null );
		call( 'wzcga_disconnect' )
			.then( () => window.location.reload() )
			.catch( ( e ) => {
				setError( e.message );
				setIsBusy( false );
			} );
	};

	const actionArea = el(
		HStack,
		{ spacing: 3, expanded: false },
		connected &&
			el(
				Text,
				{ style: { color: 'var(--wp-components-color-accent-darker-10, #008a20)' } },
				__( 'Connected', DOMAIN )
			),
		connected
			? el(
					Button,
					{ variant: 'tertiary', size: 'compact', isDestructive: true, isBusy, disabled: isBusy, onClick: disconnect },
					__( 'Disconnect', DOMAIN )
			  )
			: el( Button, { variant: 'secondary', size: 'compact', onClick: () => setIsOpen( true ) }, __( 'Sign in with ChatGPT', DOMAIN ) )
	);

	const account = connected && config.email
		? el(
				Text,
				{ variant: 'muted', size: 12 },
				config.plan
					? /* translators: 1: ChatGPT account email, 2: plan name. */ sprintf( __( 'Signed in as %1$s (%2$s plan).', DOMAIN ), config.email, config.plan )
					: /* translators: %s: ChatGPT account email. */ sprintf( __( 'Signed in as %s.', DOMAIN ), config.email )
		  )
		: null;

	return el(
		ConnectorItem,
		{ className: 'connector-item--webberzone-chatgpt-account', logo, name, description, actionArea },
		account,
		error && el( Notice, { status: 'error', isDismissible: false }, error ),
		isOpen &&
			el( SignInModal, {
				onClose: () => setIsOpen( false ),
				onConnected: () => window.location.reload(),
			} )
	);
}

if ( config.slug ) {
	registerConnector( config.slug, { render: ChatGptAccountConnector } );
}
