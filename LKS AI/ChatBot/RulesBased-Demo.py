import re
import random

class RuleBot:
    ### Potential negative responses ###
    negative_responses = ["no", "nope", "nah", "not a chance", "sorry", "I don't think"]
    ### exit conversation keywords ###
    exit_commands = ["quit", "pause", "exit", "goodbye", "bye", "later"]
    ### Random starter questions ###
    random_questions = [
        "What do you want to talk about?",
        "What's on your mind?",
        "What are you feeling right now?",
        "What made you happy today?",
        "Is there anything you want to discuss?"
    ]
def __init__(self):
    self