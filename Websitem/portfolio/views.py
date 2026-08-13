from django.http import Http404
from django.shortcuts import render
from django.urls import reverse
from django.utils.translation import get_language

from .data import get_project, site


def home(request):
    content = site(get_language())
    profile = content["profile"]
    projects = content["projects"]
    all_skills = [item for group in profile["skills"] for item in group["items"]]
    showcase_items = [
        {
            "src": p["cover"],
            "alt": p["name"],
            "href": reverse("portfolio:project_detail", args=[p["slug"]]),
        }
        for p in projects
    ]
    return render(request, "portfolio/home.html", {
        "profile": profile,
        "projects": projects,
        "all_skills": all_skills,
        "showcase_items": showcase_items,
    })


def project_list(request):
    return render(request, "portfolio/projects.html", {
        "projects": site(get_language())["projects"],
    })


def project_detail(request, slug):
    project = get_project(get_language(), slug)
    if project is None:
        raise Http404("Project not found")
    return render(request, "portfolio/project_detail.html", {
        "project": project,
        "accent": project["accent"],
    })
