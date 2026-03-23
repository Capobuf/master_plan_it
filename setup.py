from setuptools import setup, find_packages

setup(
    name="master_plan_it",
    version="0.1.0",
    description="vCIO multi-tenant budgeting & actuals management (MPIT).",
    author="DOT",
    packages=find_packages(),
    zip_safe=False,
    include_package_data=True,
    # No runtime Python dependencies: Frappe and its stack are provided by the bench environment.
    install_requires=[],
)
