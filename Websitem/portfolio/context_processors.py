from django.urls import translate_url
from django.utils.translation import get_language

from .data import site


def language(request):
    """The template chrome and the switcher.

    `translate_url` rewrites whatever page the visitor is on into the other
    language, so the switcher lands on the same page rather than the homepage.
    """
    lang = get_language()
    other = "en" if lang == "tr" else "tr"
    return {
        "ui": site(lang)["ui"],
        "lang": lang,
        "other_lang": other,
        "other_lang_url": translate_url(request.get_full_path(), other),
    }
