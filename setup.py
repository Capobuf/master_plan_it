from setuptools import setup, find_packages

with open("requirements.txt") as f:
    install_requires = f.read().strip().split("\n")

setup(
    name="master_plan_it",
    version="0.1.0",
    description="vCIO multi-tenant budgeting & actuals management (MPIT).",
    author="DOT",
    author_email="n/a",
    packages=find_packages(),
    zip_safe=False,
    include_package_data=True,
    install_requires=install_requires,
)
