( function () {
	'use strict';

	var cfg = mw.config.get( 'aiTranslationStatus' );
	if ( !cfg || !cfg.enabled ) {
		return;
	}

	var STATUS_MACHINE = 'machine';
	var STATUS_REVIEWED = 'reviewed';
	var STATUS_OUTDATED = 'outdated';
	var STATUS_UNKNOWN = 'unknown';
	var HIDE_MACHINE_KEY = 'ai_hide_machine_translation_banner';
	var MACHINE_DISCLAIMER_BY_LANG = {
		sr: 'Ova stranica je automatski prevedena. Ovaj prevod moze sadrzati greske ili netacnosti.',
		it: 'Questa pagina e stata tradotta automaticamente. Questa traduzione puo contenere errori o imprecisioni.',
		de: 'Diese Seite wurde automatisch ubersetzt. Diese Ubersetzung kann Fehler oder Ungenauigkeiten enthalten.',
		es: 'Esta pagina fue traducida automaticamente. Esta traduccion puede contener errores o inexactitudes.',
		fr: 'Cette page a ete traduite automatiquement. Cette traduction peut contenir des erreurs ou des imprecisions.',
		nl: 'Deze pagina is automatisch vertaald. Deze vertaling kan fouten of onnauwkeurigheden bevatten.',
		he: 'דף זה תורגם אוטומטית. תרגום זה עשוי להכיל שגיאות או אי־דיוקים.',
		da: 'Denne side blev oversat automatisk. Denne oversaettelse kan indeholde fejl eller unojagtigheder.',
		pt: 'Esta pagina foi traduzida automaticamente. Esta traducao pode conter erros ou imprecisoes.',
		pl: 'Ta strona zostala przetlumaczona automatycznie. To tlumaczenie moze zawierac bledy lub niescislosci.',
		el: 'Αυτή η σελίδα μεταφράστηκε αυτόματα. Αυτή η μετάφραση μπορεί να περιέχει λάθη ή ανακρίβειες.',
		hu: 'Ezt az oldalt automatikusan leforditottuk. Ez a forditas hiba kat vagy pontatlansagokat tartalmazhat.',
		sv: 'Den har sidan oversattes automatiskt. Den har oversattningen kan innehalla fel eller felaktigheter.',
		fi: 'Tama sivu on kaannetty automaattisesti. Tama kaannos voi sisaltaa virheita tai epatarkkuuksia.',
		sk: 'Tato stranka bola automaticky prelozena. Tento preklad moze obsahovat chyby alebo nepresnosti.',
		hr: 'Ova stranica je automatski prevedena. Ovaj prijevod moze sadrzavati pogreske ili netocnosti.',
		id: 'Halaman ini diterjemahkan secara otomatis. Terjemahan ini mungkin mengandung kesalahan atau ketidakakuratan.',
		ar: 'تمت ترجمة هذه الصفحة تلقائياً. قد تحتوي هذه الترجمة على أخطاء أو عدم دقة.',
		hi: 'यह पृष्ठ स्वचालित रूप से अनुवादित किया गया है। इस अनुवाद में त्रुटियाँ या अशुद्धियाँ हो सकती हैं।',
		no: 'Denne siden ble automatisk oversatt. Denne oversettelsen kan inneholde feil eller unoyaktigheter.',
		cs: 'Tato stranka byla automaticky prelozena. Tento preklad muze obsahovat chyby nebo nepresnosti.',
		ko: '이 페이지는 자동 번역되었습니다. 이 번역에는 오류나 부정확한 내용이 있을 수 있습니다.',
		ja: 'このページは自動翻訳されました。この翻訳には誤りや不正確さが含まれる場合があります。',
		ka: 'ეს გვერდი ავტომატურად იქნა თარგმნილი. ამ თარგმანს შეიძლება ჰქონდეს შეცდომები ან უზუსტობები.',
		ro: 'Aceasta pagina a fost tradusa automat. Aceasta traducere poate contine erori sau inexactitati.',
		sl: 'Ta stran je bila samodejno prevedena. Ta prevod lahko vsebuje napake ali netocnosti.',
		lb: 'Des Säit gouf automatesch iwwersat. Dës Iwwersetzung kann Feeler oder Ongenauegkeeten enthalen.',
		th: 'หน้านี้ถูกแปลโดยอัตโนมัติ การแปลนี้อาจมีข้อผิดพลาดหรือความไม่ถูกต้อง',
		is: 'Thessi sida var sjalfvirkt thydd. Thessi thyding kann innihaldid villur eda onakvaemni.',
		vi: 'Trang nay duoc dich tu dong. Ban dich nay co the chua loi hoac thieu chinh xac.',
		zu: 'Leli khasi lihunyushwe ngokuzenzakalelayo. Lolu hlelo lokuhumusha lungase luqukathe amaphutha noma ukungaqondile.',
		zh: '此页面为自动翻译。该翻译可能包含错误或不准确之处。',
		ru: 'Эта страница была автоматически переведена. Этот перевод может содержать ошибки или неточности.',
		uk: 'Цю сторінку перекладено автоматично. Цей переклад може містити помилки або неточності.',
		fa: 'این صفحه به صورت خودکار ترجمه شده است. این ترجمه ممکن است حاوی خطاها یا نادقیق‌ها باشد.',
		gu: 'આ પાનું આપમેળે અનુવાદિત થયું છે. આ અનુવાદમાં ભૂલો અથવા અચોક્કસતાઓ હોઈ શકે છે.',
		ta: 'இந்தப் பக்கம் தானாக மொழிபெயர்க்கப்பட்டுள்ளது. இந்த மொழிபெயர்ப்பில் பிழைகள் அல்லது துல்லியமின்மை இருக்கலாம்.',
		te: 'ఈ పేజీ ఆటోమేటిక్‌గా అనువదించబడింది. ఈ అనువాదంలో తప్పులు లేదా అస్పష్టతలు ఉండవచ్చు.',
		mr: 'हा पृष्ठ स्वयंचलितपणे अनुवादित केला आहे. या अनुवादात चुका किंवा अचूकतेचा अभाव असू शकतो.',
		tr: 'Bu sayfa otomatik olarak cevrildi. Bu ceviri hatalar veya yanlisliklar icerebilir.',
		ur: 'یہ صفحہ خودکار طور پر ترجمہ کیا گیا ہے۔ اس ترجمے میں غلطیاں یا عدم درستگی ہو سکتی ہے۔',
		bn: 'এই পৃষ্ঠাটি স্বয়ংক্রিয়ভাবে অনুবাদ করা হয়েছে। এই অনুবাদে ভুল বা অযথার্থতা থাকতে পারে।',
		jv: 'Kaca iki diterjemahake kanthi otomatis. Terjemahan iki bisa uga ngemot kesalahan utawa ketidakakuratan.',
		en: 'This page was automatically translated. This translation may contain errors or inaccuracies.'
	};
	var MACHINE_LINK_TEXT_BY_LANG = {
		sr: 'urediti stranicu',
		it: 'modificando la pagina',
		de: 'die Seite bearbeiten',
		es: 'editando la pagina',
		fr: 'modifiant la page',
		nl: 'de pagina te bewerken',
		he: 'עריכת הדף',
		da: 'redigere siden',
		pt: 'editando a pagina',
		pl: 'edytujac strone',
		el: 'επεξεργαζόμενοι τη σελίδα',
		hu: 'szerkeszted az oldalt',
		sv: 'redigera sidan',
		fi: 'muokkaamalla sivua',
		sk: 'upravovanim stranky',
		hr: 'uredivanjem stranice',
		id: 'mengedit halaman',
		ar: 'تحرير الصفحة',
		hi: 'पृष्ठ संपादित करके',
		no: 'redigere siden',
		cs: 'upravou stranky',
		ko: '페이지를 편집',
		ja: 'ページを編集する',
		ka: 'გვერდის რედაქტირებით',
		ro: 'editand pagina',
		sl: 'urejanjem strani',
		lb: "d'Sait aennert",
		th: 'แก้ไขหน้า',
		is: 'breyta sidunni',
		vi: 'chinh sua trang',
		zu: 'uhlele ikhasi',
		zh: '编辑页面',
		ru: 'редактируя страницу',
		uk: 'редагуючи сторінку',
		fa: 'ویرایش صفحه',
		gu: 'પાનું સંપાદિત કરીને',
		ta: 'பக்கத்தைத் திருத்துவது',
		te: 'పేజీని సవరించడం',
		mr: 'पृष्ठ संपादित',
		tr: 'sayfayi duzenleyerek',
		ur: 'صفحہ میں ترمیم',
		bn: 'পৃষ্ঠা সম্পাদনা করে',
		jv: 'nyunting kaca',
		en: 'editing the page'
	};

	function getLangCode() {
		var code = ( mw.config.get( 'wgUserLanguage' ) || 'en' ).toLowerCase();
		if ( MACHINE_DISCLAIMER_BY_LANG[code] ) {
			return code;
		}
		var base = code.split( '-' )[0];
		return MACHINE_DISCLAIMER_BY_LANG[base] ? base : 'en';
	}

	function getStorage() {
		try {
			return window.localStorage;
		} catch ( e ) {
			return null;
		}
	}

	function isMachineBannerHidden() {
		var storage = getStorage();
		return !!( storage && storage.getItem( HIDE_MACHINE_KEY ) === '1' );
	}

	function hideMachineBanner() {
		var storage = getStorage();
		if ( storage ) {
			storage.setItem( HIDE_MACHINE_KEY, '1' );
		}
	}

	function findLanguageSelector() {
		var selectors = [
			'.mw-interlanguage-selector',
			'#p-lang-btn',
			'#p-lang',
			'#p-language-compact'
		];
		for ( var i = 0; i < selectors.length; i++ ) {
			var el = document.querySelector( selectors[ i ] );
			if ( el ) {
				return el;
			}
		}
		return null;
	}

	function buildTooltipText( info ) {
		var parts = [];
		parts.push( mw.message( 'aits-tooltip-status' ).text() + ': ' + getStatusLabel( info.status ) );
		if ( info.source_rev ) {
			parts.push( mw.message( 'aits-tooltip-source-rev' ).text() + ': ' + info.source_rev );
		}
		if ( info.outdated_source_rev ) {
			parts.push( mw.message( 'aits-tooltip-outdated-rev' ).text() + ': ' + info.outdated_source_rev );
		}
		if ( info.reviewed_by ) {
			parts.push( mw.message( 'aits-tooltip-reviewed-by' ).text() + ': ' + info.reviewed_by );
		}
		if ( info.reviewed_at ) {
			parts.push( mw.message( 'aits-tooltip-reviewed-at' ).text() + ': ' + info.reviewed_at );
		}
		return parts.join( '\n' );
	}

	function getStatusLabel( status ) {
		if ( status === STATUS_REVIEWED ) {
			return mw.message( 'aits-status-reviewed' ).text();
		}
		if ( status === STATUS_OUTDATED ) {
			return mw.message( 'aits-status-outdated' ).text();
		}
		if ( status === STATUS_UNKNOWN ) {
			return mw.message( 'aits-status-unknown' ).text();
		}
		return mw.message( 'aits-status-machine' ).text();
	}

	function renderDot( info ) {
		var anchor = findLanguageSelector();
		if ( !anchor ) {
			return;
		}
		if ( document.getElementById( 'ai-translation-status-dot' ) ) {
			return;
		}

		var dot = document.createElement( 'button' );
		dot.id = 'ai-translation-status-dot';
		dot.type = 'button';
		dot.className = 'ai-translation-status-dot ai-translation-status-dot-' + info.status;
		dot.setAttribute( 'aria-label', getStatusLabel( info.status ) );
		dot.setAttribute( 'title', buildTooltipText( info ) );
		dot.setAttribute( 'tabindex', '0' );

		anchor.parentNode.insertBefore( dot, anchor );
	}

	function contentContainer() {
		return document.querySelector( '#mw-content-text' ) ||
			document.querySelector( '.mw-parser-output' ) ||
			document.querySelector( '.mw-body-content' );
	}

	function sourceUrl() {
		if ( cfg.sourceTitle ) {
			return mw.util.getUrl( cfg.sourceTitle );
		}
		var page = mw.config.get( 'wgPageName' ) || '';
		return mw.util.getUrl( page.replace( /\/[a-z-]+$/i, '' ) );
	}

	function renderBanner( info ) {
		if ( info.status !== STATUS_MACHINE && info.status !== STATUS_OUTDATED ) {
			return;
		}

		if ( info.status === STATUS_MACHINE && isMachineBannerHidden() ) {
			return;
		}

		var container = contentContainer();
		if ( !container || document.getElementById( 'ai-translation-status-banner' ) ) {
			return;
		}

		var banner = document.createElement( 'div' );
		banner.id = 'ai-translation-status-banner';
		banner.className = 'ai-translation-status-banner ai-translation-status-banner-' + info.status;

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'ai-translation-status-close';
		close.textContent = 'x';
		close.setAttribute( 'aria-label', 'close' );

		var title = document.createElement( 'strong' );
		title.className = 'ai-translation-status-title';
		title.textContent = info.status === STATUS_OUTDATED ?
			mw.message( 'aits-banner-outdated-title' ).text() : '';

		var body = document.createElement( 'span' );
		body.className = 'ai-translation-status-body';
		if ( info.status === STATUS_OUTDATED ) {
			body.textContent = mw.message( 'aits-banner-outdated-body' ).text();
		} else {
			body.textContent = MACHINE_DISCLAIMER_BY_LANG[getLangCode()] || mw.message( 'aits-banner-machine-body' ).text();
		}

		var editLink = document.createElement( 'a' );
		editLink.href = mw.util.getUrl( mw.config.get( 'wgPageName' ), { action: 'edit' } );
		if ( info.status === STATUS_OUTDATED ) {
			editLink.textContent = mw.message( 'aits-banner-outdated-cta' ).text();
		} else {
			editLink.textContent = MACHINE_LINK_TEXT_BY_LANG[getLangCode()] || mw.message( 'aits-banner-machine-cta' ).text();
		}

		var actions = document.createElement( 'span' );
		actions.className = 'ai-translation-status-actions';
		actions.appendChild( editLink );

		if ( info.status === STATUS_OUTDATED ) {
			var sourceLink = document.createElement( 'a' );
			sourceLink.href = sourceUrl();
			sourceLink.textContent = mw.message( 'aits-open-source' ).text();
			actions.appendChild( document.createTextNode( ' | ' ) );
			actions.appendChild( sourceLink );
		}

		banner.appendChild( close );
		if ( title.textContent ) {
			banner.appendChild( title );
			banner.appendChild( document.createTextNode( ' ' ) );
		}
		banner.appendChild( body );
		banner.appendChild( document.createTextNode( ' ' ) );
		banner.appendChild( actions );
		container.insertBefore( banner, container.firstChild );

		close.addEventListener( 'click', function () {
			if ( info.status === STATUS_MACHINE ) {
				hideMachineBanner();
			}
			banner.remove();
		} );
	}

	function renderStatus( info ) {
		if ( !info ) {
			return;
		}
		if ( !info.status ) {
			info.status = STATUS_UNKNOWN;
		}
		renderDot( info );
		renderBanner( info );
	}

	function load() {
		new mw.Api().get( {
			action: 'aitranslationinfo',
			title: cfg.title || mw.config.get( 'wgPageName' )
		} ).then( function ( data ) {
			renderStatus( data.aitranslationinfo || {} );
		} ).catch( function () {
			// Fail silently by design.
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', load );
	} else {
		load();
	}
}() );
