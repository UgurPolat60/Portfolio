"""The smallest thing that fails if the two-language site breaks."""

# SimpleTestCase, not TestCase: this site has no database, and TestCase would
# insist on creating one to wrap each test in a transaction.
from django.test import SimpleTestCase


class BothLanguagesTests(SimpleTestCase):
    def test_every_page_answers_in_both_languages(self):
        for path in ("/", "/projelerim/", "/projelerim/rage-attack/",
                     "/en/", "/en/projelerim/", "/en/projelerim/rage-attack/"):
            with self.subTest(path=path):
                self.assertEqual(self.client.get(path).status_code, 200)

    def test_unknown_project_is_404(self):
        self.assertEqual(self.client.get("/en/projelerim/nope/").status_code, 404)

    def test_turkish_stays_turkish(self):
        page = self.client.get("/").content.decode()
        self.assertIn('<html lang="tr">', page)
        self.assertIn("Projelerim", page)
        self.assertIn("Bilgisayar Mühendisi", page)

    def test_english_is_actually_english(self):
        page = self.client.get("/en/").content.decode()
        self.assertIn('<html lang="en">', page)
        self.assertIn("Computer Engineer", page)
        # a leftover Turkish heading would mean the page is only half translated
        self.assertNotIn("Yetenekler", page)

    def test_project_bodies_are_translated_not_just_the_chrome(self):
        page = self.client.get("/en/projelerim/rage-attack/").content.decode()
        self.assertIn("five-stage rage state machine", page)
        self.assertNotIn("öfke durum makinesi", page)

    def test_the_switcher_lands_on_the_same_page(self):
        """A language switch that always drops you on the homepage is a bug."""
        tr = self.client.get("/projelerim/rage-attack/").content.decode()
        self.assertIn('href="/en/projelerim/rage-attack/"', tr)
        en = self.client.get("/en/projelerim/rage-attack/").content.decode()
        self.assertIn('href="/projelerim/rage-attack/"', en)

    def test_both_languages_offer_the_same_projects(self):
        from .data import CONTENT

        self.assertEqual(
            [p["slug"] for p in CONTENT["tr"]["projects"]],
            [p["slug"] for p in CONTENT["en"]["projects"]],
        )
        self.assertEqual(
            sorted(CONTENT["tr"]["ui"]), sorted(CONTENT["en"]["ui"]),
        )
