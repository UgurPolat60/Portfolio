from django.http import Http404
from django.shortcuts import render
from django.urls import reverse

from .data import PROFILE, PROJECTS, get_project


def home(request):
    all_skills = [item for group in PROFILE["skills"] for item in group["items"]]
    showcase_items = [
        {
            "src": p["cover"],
            "alt": p["name"],
            "href": reverse("portfolio:project_detail", args=[p["slug"]]),
        }
        for p in PROJECTS
    ]
    return render(request, "portfolio/home.html", {
        "profile": PROFILE,
        "projects": PROJECTS,
        "all_skills": all_skills,
        "showcase_items": showcase_items,
    })


def project_list(request):
    return render(request, "portfolio/projects.html", {
        "projects": PROJECTS,
    })


def project_detail(request, slug):
    project = get_project(slug)
    if project is None:
        raise Http404("Project not found")
    return render(request, "portfolio/project_detail.html", {
        "project": project,
        "accent": project["accent"],
    })
