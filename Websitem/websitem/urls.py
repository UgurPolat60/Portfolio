from django.conf.urls.i18n import i18n_patterns
from django.urls import include, path

# Turkish keeps the bare URLs it always had; English lives under /en/.
urlpatterns = i18n_patterns(
    path('', include('portfolio.urls')),
    prefix_default_language=False,
)
